<?php
      
namespace amqphp\protocol\v0_9_1\connection;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartServerPropertiesField extends \amqphp\protocol\v0_9_1\PeerPropertiesDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'server-properties'; }
    function getSpecFieldDomain() { return 'peer-properties'; }

}