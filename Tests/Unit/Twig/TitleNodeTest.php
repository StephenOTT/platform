<?php

namespace Oro\Bundle\NavigationBundle\Tests\Unit\Twig;

use Oro\Bundle\NavigationBundle\Twig\TitleNode;

class TitleNodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $node;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $compiler;

    /**
     * @var TitleNode
     */
    private $titleNode;

    /**
     * Set up test environment
     */
    public function setUp()
    {
        $this->node = $this->getMock('Twig_Node');
        $this->compiler = $this->getMockBuilder('Twig_Compiler')
                               ->disableOriginalConstructor()
                               ->getMock();

        $this->titleNode = new TitleNode($this->node);
    }

    /**
     * Tests error in twig tag call
     *
     * @expectedException \Twig_Error_Syntax
     */
    public function testFailedCompile()
    {
        $this->node->expects($this->once())
                   ->method('getIterator')
                   ->will($this->returnValue(array()));

        $this->titleNode->compile($this->compiler);
    }

    /**
     * Tests success node compiling
     */
    public function testSuccessCompile()
    {
        $exprMock = $this->getMockBuilder('Twig_Node_Expression_Array')
                         ->disableOriginalConstructor()
                         ->getMock();

        $this->node->expects($this->once())
            ->method('getIterator')
            ->will($this->returnValue(array($exprMock)));

        $this->compiler->expects($this->exactly(2))
                       ->method('raw')
                       ->will($this->returnValue($this->compiler));

        $this->compiler->expects($this->once())
                       ->method('write')
                       ->will($this->returnValue($this->compiler));

        $this->compiler->expects($this->once())
                       ->method('subcompile')
                       ->with($this->isInstanceOf(get_class($exprMock)))
                       ->will($this->returnValue($this->compiler));

        $this->titleNode->compile($this->compiler);
    }
}
