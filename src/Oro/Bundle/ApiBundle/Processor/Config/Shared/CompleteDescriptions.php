<?php

namespace Oro\Bundle\ApiBundle\Processor\Config\Shared;

use Oro\Bundle\ApiBundle\Util\ValueNormalizerUtil;
use Symfony\Component\Translation\TranslatorInterface;

use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;
use Oro\Bundle\ApiBundle\ApiDoc\EntityDescriptionProvider;
use Oro\Bundle\ApiBundle\ApiDoc\Parser\ApiDocMdParser;
use Oro\Bundle\ApiBundle\ApiDoc\ResourceDocProviderInterface;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Config\FiltersConfig;
use Oro\Bundle\ApiBundle\Model\Label;
use Oro\Bundle\ApiBundle\Processor\Config\ConfigContext;

/**
 * Adds human-readable descriptions for the entity, fields and filters.
 */
class CompleteDescriptions implements ProcessorInterface
{
    /** @var EntityDescriptionProvider */
    protected $entityDescriptionProvider;

    /** @var ResourceDocProviderInterface */
    protected $resourceDocProvider;

    /** @var ApiDocMdParser */
    protected $apiDocMdParser;

    /** @var TranslatorInterface */
    protected $translator;

    /**
     * @param EntityDescriptionProvider    $entityDescriptionProvider
     * @param ResourceDocProviderInterface $resourceDocProvider
     * @param ApiDocMdParser               $apiDocMdParser
     * @param TranslatorInterface          $translator
     */
    public function __construct(
        EntityDescriptionProvider $entityDescriptionProvider,
        ResourceDocProviderInterface $resourceDocProvider,
        ApiDocMdParser $apiDocMdParser,
        TranslatorInterface $translator
    ) {
        $this->entityDescriptionProvider = $entityDescriptionProvider;
        $this->resourceDocProvider = $resourceDocProvider;
        $this->apiDocMdParser = $apiDocMdParser;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var ConfigContext $context */

        $targetAction = $context->getTargetAction();
        if (!$targetAction) {
            // descriptions cannot be set for undefined target action
            return;
        }

        $entityClass = $context->getClassName();
        $definition = $context->getResult();

        $this->setDescriptionForEntity(
            $definition,
            $entityClass,
            $targetAction,
            $context->isCollection(),
            $context->getAssociationName()
        );
        $this->setDescriptionsForFields($definition, $entityClass, $targetAction);
        $filters = $context->getFilters();
        if (null !== $filters) {
            $this->setDescriptionsForFilters($filters, $definition, $entityClass, $targetAction);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $entityClass
     * @param string                 $targetAction
     * @param bool                   $isCollection
     * @param string                 $associationName
     */
    protected function setDescriptionForEntity(
        EntityDefinitionConfig $definition,
        $entityClass,
        $targetAction,
        $isCollection,
        $associationName
    ) {
        $entityDescription = false;
        $associationDescription = false;

        if (!$definition->hasDescription()) {
            if ($associationName) {
                $associationDescription = $this->getAssociationDescription($associationName);
                $this->setDescriptionForSubresource(
                    $definition,
                    $associationDescription,
                    $targetAction,
                    $isCollection
                );
            } else {
                $entityDescription = $this->getEntityDescription($entityClass, $isCollection);
                if ($entityDescription) {
                    $this->setDescriptionForResource($definition, $targetAction, $entityDescription);
                }
            }
        } else {
            $description = $definition->getDescription();
            if ($description instanceof Label) {
                $definition->setDescription($this->trans($description));
            }
        }

        if ($definition->has(EntityDefinitionConfig::DOCUMENTATION_RESOURCE)) {
            $this->apiDocMdParser->parseDocumentationResource(
                $definition->get(EntityDefinitionConfig::DOCUMENTATION_RESOURCE)
            );
        }

        if ($definition->hasDocumentation()) {
            $this->apiDocMdParser->parseDocumentationResource($definition->getDocumentation());
        }

        $loadedDocumentation = $this->apiDocMdParser->getDocumentation($entityClass, 'actions', $targetAction);
        if ($loadedDocumentation) {
            $definition->setDocumentation($loadedDocumentation);
        } else {
            if ($associationName) {
                if (false === $associationDescription) {
                    $associationDescription = $this->getAssociationDescription($associationName);
                }
                $this->setDocumentationForSubresource(
                    $definition,
                    $associationDescription,
                    $targetAction,
                    $isCollection
                );
            } else {
                if (false === $entityDescription) {
                    $entityDescription = $this->getEntityDescription($entityClass, $isCollection);
                }
                if ($entityDescription) {
                    $this->setDocumentationForResource($definition, $targetAction, $entityDescription);
                }
            }
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $targetAction
     * @param string                 $entityDescription
     */
    protected function setDescriptionForResource(
        EntityDefinitionConfig $definition,
        $targetAction,
        $entityDescription
    ) {
        $description = $this->resourceDocProvider->getResourceDescription($targetAction, $entityDescription);
        if ($description) {
            $definition->setDescription($description);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $targetAction
     * @param string                 $entityDescription
     */
    protected function setDocumentationForResource(
        EntityDefinitionConfig $definition,
        $targetAction,
        $entityDescription
    ) {
        $documentation = $this->resourceDocProvider->getResourceDocumentation($targetAction, $entityDescription);
        if ($documentation) {
            $definition->setDocumentation($documentation);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $associationDescription
     * @param string                 $targetAction
     * @param bool                   $isCollection
     */
    protected function setDescriptionForSubresource(
        EntityDefinitionConfig $definition,
        $associationDescription,
        $targetAction,
        $isCollection
    ) {
        $description = $this->resourceDocProvider->getSubresourceDescription(
            $targetAction,
            $associationDescription,
            $isCollection
        );
        if ($description) {
            $definition->setDescription($description);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $associationDescription
     * @param string                 $targetAction
     * @param bool                   $isCollection
     */
    protected function setDocumentationForSubresource(
        EntityDefinitionConfig $definition,
        $associationDescription,
        $targetAction,
        $isCollection
    ) {
        $documentation = $this->resourceDocProvider->getSubresourceDocumentation(
            $targetAction,
            $associationDescription,
            $isCollection
        );
        if ($documentation) {
            $definition->setDocumentation($documentation);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $entityClass
     * @param string                 $targetAction
     * @param string|null            $fieldPrefix
     */
    protected function setDescriptionsForFields(
        EntityDefinitionConfig $definition,
        $entityClass,
        $targetAction,
        $fieldPrefix = null
    ) {
        $fields = $definition->getFields();
        foreach ($fields as $fieldName => $field) {
            if (!$field->hasDescription()) {
                $loadedDescription = $this->apiDocMdParser->getDocumentation(
                    $entityClass,
                    'fields',
                    $fieldName,
                    $targetAction
                );
                if ($loadedDescription) {
                    $field->setDescription($loadedDescription);
                    continue;
                }

                $propertyPath = $field->getPropertyPath($fieldName);
                if ($fieldPrefix) {
                    $propertyPath = $fieldPrefix . $propertyPath;
                }
                $description = $this->entityDescriptionProvider->getFieldDescription($entityClass, $propertyPath);
                if ($description) {
                    $field->setDescription($description);
                }
            } else {
                $label = $field->getDescription();
                if ($label instanceof Label) {
                    $field->setDescription($this->trans($label));
                } else {
                    $loadedDescription = $this->apiDocMdParser->getDocumentation(
                        $entityClass,
                        'fields',
                        $fieldName,
                        $targetAction,
                        $label
                    );
                    if ($loadedDescription) {
                        $field->setDescription($loadedDescription);
                        continue;
                    }
                }
            }
            $targetEntity = $field->getTargetEntity();
            if ($targetEntity && $targetEntity->hasFields()) {
                $targetClass = $field->getTargetClass();
                if ($targetClass) {
                    $this->setDescriptionsForFields($targetEntity, $targetClass, $targetAction);
                } else {
                    $propertyPath = $field->getPropertyPath($fieldName);
                    $this->setDescriptionsForFields($targetEntity, $entityClass, $propertyPath . '.');
                }
            }
        }
    }

    /**
     * @param FiltersConfig          $filters
     * @param EntityDefinitionConfig $definition
     * @param string                 $entityClass
     * @param string                 $targetAction
     */
    protected function setDescriptionsForFilters(
        FiltersConfig $filters,
        EntityDefinitionConfig $definition,
        $entityClass,
        $targetAction
    ) {
        $fields = $filters->getFields();
        foreach ($fields as $fieldName => $field) {
            if (!$field->hasDescription()) {
                $loadedDescription = $this->apiDocMdParser->getDocumentation(
                    $entityClass,
                    'filters',
                    $fieldName,
                    $targetAction
                );
                if ($loadedDescription) {
                    $field->setDescription($loadedDescription);
                    continue;
                }

                $fieldsDefinition = $definition->getField($fieldName);
                if ($fieldsDefinition && $fieldsDefinition->hasTargetEntity()) {
                    $description = sprintf(
                        'Filter \'%s\' by \'%s\' relation value, accepts \'%s\' type values.',
                        ValueNormalizerUtil::humanizeClassName($entityClass),
                        $fieldName,
                        $field->getDataType()
                    );
                    $field->setDescription($description);
                    continue;
                }

                $propertyPath = $field->getPropertyPath($fieldName);
                $description = $this->entityDescriptionProvider->getFieldDescription($entityClass, $propertyPath);
                if ($description) {
                    $field->setDescription($description);
                }
            } else {
                $description = $field->getDescription();
                if ($description instanceof Label) {
                    $field->setDescription($this->trans($description));
                } else {
                    $loadedDescription = $this->apiDocMdParser->getDocumentation(
                        $entityClass,
                        'filters',
                        $fieldName,
                        $targetAction,
                        $description
                    );
                    if ($loadedDescription) {
                        $field->setDescription($loadedDescription);
                        continue;
                    }
                }
            }
        }
    }

    /**
     * @param string $entityClass
     * @param bool   $isCollection
     *
     * @return string|null
     */
    protected function getEntityDescription($entityClass, $isCollection)
    {
        return $isCollection
            ? $this->entityDescriptionProvider->getEntityPluralDescription($entityClass)
            : $this->entityDescriptionProvider->getEntityDescription($entityClass);
    }

    /**
     * @param string $associationName
     *
     * @return string
     */
    protected function getAssociationDescription($associationName)
    {
        return $this->entityDescriptionProvider->humanizeAssociationName($associationName);
    }

    /**
     * @param Label $label
     *
     * @return string|null
     */
    protected function trans(Label $label)
    {
        return $label->trans($this->translator) ? : null;
    }
}
