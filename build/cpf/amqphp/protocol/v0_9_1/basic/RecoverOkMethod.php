<?php
namespace amqphp\protocol\v0_9_1\basic;
/** Ampq binding code, generated from doc version 0.9.1 */
class RecoverOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'basic';
    protected $name = 'recover-ok';
    protected $index = 111;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\basic\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\basic\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}