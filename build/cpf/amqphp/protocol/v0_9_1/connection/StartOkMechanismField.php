<?php
      
namespace amqphp\protocol\v0_9_1\connection;
	
/** Ampq binding code, generated from doc version 0.9.1 */

class StartOkMechanismField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'mechanism'; }
    function getSpecFieldDomain() { return 'shortstr'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}