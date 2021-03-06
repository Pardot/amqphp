<?php
namespace amqphp\protocol\v0_9_1\queue;

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindArgumentsField extends \amqphp\protocol\v0_9_1\TableDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'arguments'; }
    function getSpecFieldDomain() { return 'table'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class BindMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'bind';
    protected $index = 20;
    protected $synchronous = true;
    protected $responseMethods = array('bind-ok');
    protected $fields = array('reserved-1', 'queue', 'exchange', 'routing-key', 'no-wait', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class BindOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'bind-ok';
    protected $index = 21;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindQueueField extends \amqphp\protocol\v0_9_1\QueueNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'queue'; }
    function getSpecFieldDomain() { return 'queue-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class BindRoutingKeyField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'routing-key'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareArgumentsField extends \amqphp\protocol\v0_9_1\TableDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'arguments'; }
    function getSpecFieldDomain() { return 'table'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareAutoDeleteField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'auto-delete'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareDurableField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'durable'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareExclusiveField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exclusive'; }
    function getSpecFieldDomain() { return 'bit'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class DeclareMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'declare';
    protected $index = 10;
    protected $synchronous = true;
    protected $responseMethods = array('declare-ok');
    protected $fields = array('reserved-1', 'queue', 'passive', 'durable', 'exclusive', 'auto-delete', 'no-wait', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareOkConsumerCountField extends \amqphp\protocol\v0_9_1\LongDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'consumer-count'; }
    function getSpecFieldDomain() { return 'long'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareOkMessageCountField extends \amqphp\protocol\v0_9_1\MessageCountDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'message-count'; }
    function getSpecFieldDomain() { return 'message-count'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class DeclareOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'declare-ok';
    protected $index = 11;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('queue', 'message-count', 'consumer-count');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareOkQueueField extends \amqphp\protocol\v0_9_1\QueueNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'queue'; }
    function getSpecFieldDomain() { return 'queue-name'; }

    function validate($subject) {
        return (parent::validate($subject) && ! is_null($subject));
    }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclarePassiveField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'passive'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareQueueField extends \amqphp\protocol\v0_9_1\QueueNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'queue'; }
    function getSpecFieldDomain() { return 'queue-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeclareReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteIfEmptyField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'if-empty'; }
    function getSpecFieldDomain() { return 'bit'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteIfUnusedField extends \amqphp\protocol\v0_9_1\BitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'if-unused'; }
    function getSpecFieldDomain() { return 'bit'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class DeleteMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'delete';
    protected $index = 40;
    protected $synchronous = true;
    protected $responseMethods = array('delete-ok');
    protected $fields = array('reserved-1', 'queue', 'if-unused', 'if-empty', 'no-wait');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteOkMessageCountField extends \amqphp\protocol\v0_9_1\MessageCountDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'message-count'; }
    function getSpecFieldDomain() { return 'message-count'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class DeleteOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'delete-ok';
    protected $index = 41;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('message-count');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteQueueField extends \amqphp\protocol\v0_9_1\QueueNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'queue'; }
    function getSpecFieldDomain() { return 'queue-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class DeleteReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

abstract class FieldFactory  extends \amqphp\protocol\abstrakt\FieldFactory
{
    protected static $Cache = array(array('reserved-1', 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareReserved1Field'),array('queue', 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareQueueField'),array('passive', 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclarePassiveField'),array('durable', 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareDurableField'),array('exclusive', 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareExclusiveField'),array('auto-delete', 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareAutoDeleteField'),array('no-wait', 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareNoWaitField'),array('arguments', 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareArgumentsField'),array('queue', 'declare-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareOkQueueField'),array('message-count', 'declare-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareOkMessageCountField'),array('consumer-count', 'declare-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareOkConsumerCountField'),array('reserved-1', 'bind', '\\amqphp\\protocol\\v0_9_1\\queue\\BindReserved1Field'),array('queue', 'bind', '\\amqphp\\protocol\\v0_9_1\\queue\\BindQueueField'),array('exchange', 'bind', '\\amqphp\\protocol\\v0_9_1\\queue\\BindExchangeField'),array('routing-key', 'bind', '\\amqphp\\protocol\\v0_9_1\\queue\\BindRoutingKeyField'),array('no-wait', 'bind', '\\amqphp\\protocol\\v0_9_1\\queue\\BindNoWaitField'),array('arguments', 'bind', '\\amqphp\\protocol\\v0_9_1\\queue\\BindArgumentsField'),array('reserved-1', 'unbind', '\\amqphp\\protocol\\v0_9_1\\queue\\UnbindReserved1Field'),array('queue', 'unbind', '\\amqphp\\protocol\\v0_9_1\\queue\\UnbindQueueField'),array('exchange', 'unbind', '\\amqphp\\protocol\\v0_9_1\\queue\\UnbindExchangeField'),array('routing-key', 'unbind', '\\amqphp\\protocol\\v0_9_1\\queue\\UnbindRoutingKeyField'),array('arguments', 'unbind', '\\amqphp\\protocol\\v0_9_1\\queue\\UnbindArgumentsField'),array('reserved-1', 'purge', '\\amqphp\\protocol\\v0_9_1\\queue\\PurgeReserved1Field'),array('queue', 'purge', '\\amqphp\\protocol\\v0_9_1\\queue\\PurgeQueueField'),array('no-wait', 'purge', '\\amqphp\\protocol\\v0_9_1\\queue\\PurgeNoWaitField'),array('message-count', 'purge-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\PurgeOkMessageCountField'),array('reserved-1', 'delete', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteReserved1Field'),array('queue', 'delete', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteQueueField'),array('if-unused', 'delete', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteIfUnusedField'),array('if-empty', 'delete', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteIfEmptyField'),array('no-wait', 'delete', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteNoWaitField'),array('message-count', 'delete-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteOkMessageCountField'));
}

abstract class MethodFactory extends \amqphp\protocol\abstrakt\MethodFactory
{
    protected static $Cache = array(array(10, 'declare', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareMethod'),array(11, 'declare-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\DeclareOkMethod'),array(20, 'bind', '\\amqphp\\protocol\\v0_9_1\\queue\\BindMethod'),array(21, 'bind-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\BindOkMethod'),array(50, 'unbind', '\\amqphp\\protocol\\v0_9_1\\queue\\UnbindMethod'),array(51, 'unbind-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\UnbindOkMethod'),array(30, 'purge', '\\amqphp\\protocol\\v0_9_1\\queue\\PurgeMethod'),array(31, 'purge-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\PurgeOkMethod'),array(40, 'delete', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteMethod'),array(41, 'delete-ok', '\\amqphp\\protocol\\v0_9_1\\queue\\DeleteOkMethod'));
}

/** Ampq binding code, generated from doc version 0.9.1 */
class PurgeMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'purge';
    protected $index = 30;
    protected $synchronous = true;
    protected $responseMethods = array('purge-ok');
    protected $fields = array('reserved-1', 'queue', 'no-wait');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = true;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PurgeNoWaitField extends \amqphp\protocol\v0_9_1\NoWaitDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'no-wait'; }
    function getSpecFieldDomain() { return 'no-wait'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PurgeOkMessageCountField extends \amqphp\protocol\v0_9_1\MessageCountDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'message-count'; }
    function getSpecFieldDomain() { return 'message-count'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class PurgeOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'purge-ok';
    protected $index = 31;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array('message-count');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PurgeQueueField extends \amqphp\protocol\v0_9_1\QueueNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'queue'; }
    function getSpecFieldDomain() { return 'queue-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class PurgeReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class QueueClass extends \amqphp\protocol\abstrakt\XmlSpecClass
{
    protected $name = 'queue';
    protected $index = 50;
    protected $fields = array();
    protected $methods = array(10 => 'declare',11 => 'declare-ok',20 => 'bind',21 => 'bind-ok',50 => 'unbind',51 => 'unbind-ok',30 => 'purge',31 => 'purge-ok',40 => 'delete',41 => 'delete-ok');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindArgumentsField extends \amqphp\protocol\v0_9_1\TableDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'arguments'; }
    function getSpecFieldDomain() { return 'table'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindExchangeField extends \amqphp\protocol\v0_9_1\ExchangeNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'exchange'; }
    function getSpecFieldDomain() { return 'exchange-name'; }

}

/** Ampq binding code, generated from doc version 0.9.1 */
class UnbindMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'unbind';
    protected $index = 50;
    protected $synchronous = true;
    protected $responseMethods = array('unbind-ok');
    protected $fields = array('reserved-1', 'queue', 'exchange', 'routing-key', 'arguments');
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

/** Ampq binding code, generated from doc version 0.9.1 */
class UnbindOkMethod extends \amqphp\protocol\abstrakt\XmlSpecMethod
{
    protected $class = 'queue';
    protected $name = 'unbind-ok';
    protected $index = 51;
    protected $synchronous = true;
    protected $responseMethods = array();
    protected $fields = array();
    protected $methFact = '\\amqphp\\protocol\\v0_9_1\\queue\\MethodFactory';
    protected $fieldFact = '\\amqphp\\protocol\\v0_9_1\\queue\\FieldFactory';
    protected $classFact = '\\amqphp\\protocol\\v0_9_1\\ClassFactory';
    protected $content = false;
    protected $hasNoWait = false;
}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindQueueField extends \amqphp\protocol\v0_9_1\QueueNameDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'queue'; }
    function getSpecFieldDomain() { return 'queue-name'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindReserved1Field extends \amqphp\protocol\v0_9_1\ShortDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'reserved-1'; }
    function getSpecFieldDomain() { return 'short'; }

}

	
/** Ampq binding code, generated from doc version 0.9.1 */

class UnbindRoutingKeyField extends \amqphp\protocol\v0_9_1\ShortstrDomain implements \amqphp\protocol\abstrakt\XmlSpecField
{
    function getSpecFieldName() { return 'routing-key'; }
    function getSpecFieldDomain() { return 'shortstr'; }

}