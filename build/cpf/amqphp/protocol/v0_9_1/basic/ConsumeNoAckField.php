<?php
      
namespace amqphp\protocol\v0_9_1\basic;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class ConsumeNoAckField extends \amqphp\protocol\v0_9_1\NoAckDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-ack'; }
    function getSpecFieldDomain() { return 'no-ack'; }

}