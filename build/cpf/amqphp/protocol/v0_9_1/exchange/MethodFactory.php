<?php
namespace amqphp\protocol\v0_9_1\exchange;
abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'declare', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareMethod'),array(11, 'declare-ok', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeclareOkMethod'),array(20, 'delete', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeleteMethod'),array(21, 'delete-ok', '\\amqphp\\protocol\\v0_9_1\\exchange\\DeleteOkMethod'),array(30, 'bind', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindMethod'),array(31, 'bind-ok', '\\amqphp\\protocol\\v0_9_1\\exchange\\BindOkMethod'),array(40, 'unbind', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindMethod'),array(51, 'unbind-ok', '\\amqphp\\protocol\\v0_9_1\\exchange\\UnbindOkMethod'));
}