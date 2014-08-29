<?php

namespace Codebender\ApiBundle\Tests\Controller;
use Codebender\ApiBundle\Controller\DefaultController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class ProtectedDefaultControllerTester extends DefaultController {

    public function call_checkHeaders($files, $personallib)
    {
        return $this->checkHeaders($files, $personallib);
    }

}

class DefaultControllerUnitTest extends \PHPUnit_Framework_TestCase
{
    public function testCompilelibrariesAction_Success()
    {
        $controller = $this->getMock("Codebender\ApiBundle\Controller\DefaultController", array("get", "getRequest", "checkHeaders"));

        $request = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")
            ->disableOriginalConstructor()
            ->setMethods(array('getContent'))
            ->getMock();

        $container = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")
            ->disableOriginalConstructor()
            ->setMethods(array('getParameter'))
            ->getMockForAbstractClass();

        $handler = $this->getMockBuilder("Codebender\ApiBundle\Handler\DefaultHandler")
            ->disableOriginalConstructor()
            ->setMethods(array("get", "post_raw_data"))
            ->getMock();

        $controller->setContainer($container);

        $controller->expects($this->once())->method('getRequest')->will($this->returnValue($request));
        $request->expects($this->once())->method('getContent')->will($this->returnValue('{"files":[{"filename":"project.ino","content":"#include <header.h> #include \"header2.h\" "}],"format":"binary","version":"105","build":{"mcu":"atmega328p","f_cpu":"16000000L","core":"arduino","variant":"standard"},"libraries":[{"0":"header.h"}]}' ));

        $controller->expects($this->once())->method('get')->with($this->equalTo('codebender_api.handler'))->will($this->returnValue($handler));
        $container->expects($this->at(0))->method('getParameter')->with($this->equalTo('library'))->will($this->returnValue('http://library/url'));
        $container->expects($this->at(1))->method('getParameter')->with($this->equalTo('compiler'))->will($this->returnValue('http://compiler/url'));

        $controller->expects($this->once())->method('checkHeaders')->with(array(0=>array('filename'=>'project.ino','content'=>'#include <header.h> #include "header2.h" ')), array(0=>array(0=>'header.h')))->will($this->returnValue(array('libraries' => array('header'=>array(0=>array("filename"=>"header.h","content"=>""))), 'foundFiles' => array(), 'notFoundHeaders' => array())));

        $handler->expects($this->once())->method('post_raw_data')->with($this->equalTo('http://compiler/url'), $this->equalTo('{"files":[{"filename":"project.ino","content":"#include <header.h> #include \"header2.h\" "}],"format":"binary","version":"105","build":{"mcu":"atmega328p","f_cpu":"16000000L","core":"arduino","variant":"standard"},"libraries":{"header":[{"filename":"header.h","content":""}]}}'))->will($this->returnValue('{"success":true}'));

        $response = $controller->compilelibrariesAction();

        $this->assertEquals($response->getContent(), '{"library":{"header":[{"filename":"header.h","content":""}]},"foundFiles":[],"notFoundHeaders":[],"compileResponse":"{\"success\":true}"}');
    }

    public function testCheckHeaders_success()
    {
        $controller = $this->getMock("Codebender\ApiBundle\Tests\Controller\ProtectedDefaultControllerTester", array("get", "getRequest"));

        $handler = $this->getMockBuilder("Codebender\ApiBundle\Handler\DefaultHandler")
            ->disableOriginalConstructor()
            ->setMethods(array("get", "read_libraries"))
            ->getMock();

        $container = $this->getMockBuilder("Symfony\Component\DependencyInjection\ContainerInterface")
            ->disableOriginalConstructor()
            ->setMethods(array('getParameter'))
            ->getMockForAbstractClass();

        $request = $this->getMockBuilder("Symfony\Component\HttpFoundation\Request")
            ->disableOriginalConstructor()
            ->getMock();

        $controller->setContainer($container);

        $controller->expects($this->once())->method('get')->with($this->equalTo('codebender_api.handler'))->will($this->returnValue($handler));
        $handler->expects($this->once())->method('read_libraries')->with($this->equalTo(array(array('filename' => 'header.h', 'content' => ''), array('filename' => 'project.ino', 'content' => ''))))->will($this->returnValue(array('header')));

        $container->expects($this->once())->method('getParameter')->with($this->equalTo('library'))->will($this->returnValue('http://library/url'));

        $handler->expects($this->once())->method('get')->with($this->equalTo('http://library/url/fetch?library=header'))->will($this->returnValue('{"success":true,"message":"Library found","files":[{"filename":"header.h","content":""}]}'));

        $response = $controller->call_checkHeaders(array(array('filename' => 'header.h', 'content' => ''), array('filename' => 'project.ino', 'content' => '')), array());

        $this->assertEquals($response, array('libraries' => array('header'=>array(0=>array('filename'=>'header.h', 'content'=>''))), 'foundFiles' => array(0=>'header.h'), 'notFoundHeaders' => array()));
    }


}
