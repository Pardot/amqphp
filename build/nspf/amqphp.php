<?php
namespace amqphp;
/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */


use amqphp\protocol, amqphp\wire, \amqphp\persistent as pers;






class CallbackExitStrategy implements ExitStrategy
{
    private $cb;
    private $args;

    function configure ($sMode, $cb=null, $args=null) {
        if (! is_callable($cb)) {
            trigger_error("Select mode - invalid callback params", E_USER_WARNING);
            return false;
        } else {
            $this->cb = $cb;
            $this->args = $args;
            return true;
        }
    }

    function init (Connection $conn) {}

    function preSelect ($prev=null) {
        if ($prev === false) {
            return false;
        }
        if (true !== call_user_func_array($this->cb, $this->args)) {
            return false;
        } else {
            return $prev;
        }
    }

    function complete () {}
}

/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */






class Channel
{
    /** The parent Connection object */
    protected $myConn;

    /** The channel ID we're linked to */
    protected $chanId;

    /**
     * As  set  by  the  channel.flow Amqp  method,  controls  whether
     * content can be sent or not
     */
    protected $flow = true;

    /**
     * Flag set when  the underlying Amqp channel has  been closed due
     * to an exception
     */
    private $destroyed = false;

    /**
     * Set by negotiation during channel setup
     */
    protected $frameMax;

    /**
     * Used to track whether the channel.open returned OK.
     */
    protected $isOpen = false;

    /**
     * Consumers for this channel, a list of consumer / state entries,
     * each with the following data format:
     *
     * array (
     *    <amqphp\Consumer instance>,
     *    <consumer-tag or false>,
     *    <#FLAG#>,
     *    <consume params (array)>
     * )
     *
     * #FLAG# is the consumer status, this is:
     *  'READY_WAIT' - not yet started, i.e. before basic.consume/basic.consume-ok
     *  'READY' - started and ready to recieve messages
     *  'CLOSED' - previously live but now closed, receiving a basic.cancel-ok triggers this.
     */
    protected $consumers = array();

    /**
     * Channel Event Handler, set in setEventHandler().
     */
    protected $callbackHandler;

    /**
     * Store of basic.publish  sequence numbers, format:
     *     array(<seq-no> => \amqphp\Wire\Method)
     */
    protected $confirmSeqs = array();

    /**
     * Used  to track  outgoing  message sequence  numbers in  confirm
     * mode.
     */
    protected $confirmSeq = 0;

    /** Flag set during RMQ confirm mode */
    protected $confirmMode = false;


    /**
     * Use  this  value as  an  (n)ack  buffer  whilst consuming,  the
     * channel  will  group  together   this  many  (n)acks  and  send
     * responses  using   the  Amqp  'multiple'   field.   To  prevent
     * buffering,  set to  zero.   Setting  this to  a  hig value  can
     * increase throughput of consumers.
     *
     * @field   int
     */
    public $ackBuffer = 1;

    /** 
     * $ackHead  holds the  latest delivery  tag of  received messages
     * which we're  buffering as a  result of the  $ackBuffer setting.
     * Once $ackBuffer acks have accumulated, we (n)ack up to $ackHead
     */
    protected $ackHead;

    /**
     * $numPendAcks  tracks how  many outstanding  acks  are currently
     * buffered  -  once  this  value matches  the  $ackBuffer  value,
     * (n)acks messages are send to the broker.
     */
    protected $numPendAcks = 0;

    /**
     * Specifies the  kind of  responses that are  queued up  as local
     * acks, one of the CONSUMER_* consts.
     */
    protected $ackFlag;


    /**
     * Assigns a channel event handler
     */
    function setEventHandler (ChannelEventHandler $evh) {
        $this->callbackHandler = $evh;
    }

    function hasOutstandingConfirms () {
        return (bool) $this->confirmSeqs;
    }

    function setConfirmMode () {
        if ($this->confirmMode) {
            return;
        }
        $confSelect = $this->confirm('select');
        $confSelectOk = $this->invoke($confSelect);
        if (! ($confSelectOk instanceof wire\Method) ||
            $confSelectOk->amqpClass != 'confirm.select-ok') {
            throw new \Exception("Failed to set confirm mode", 8674);
        }
        $this->confirmMode = true;
    }


    function setConnection (Connection $rConn) {
        $this->myConn = $rConn;
    }

    function setChanId ($chanId) {
        $this->chanId = $chanId;
    }

    function getChanId () {
        return $this->chanId;
    }

    function setFrameMax ($frameMax) {
        $this->frameMax = $frameMax;
    }


    function initChannel () {
        $pl = $this->myConn->getProtocolLoader();
        $meth = new wire\Method($pl('ClassFactory', 'GetMethod', array('channel', 'open')), $this->chanId);
        $meth->setField('reserved-1', '');
        $resp = $this->myConn->invoke($meth);
    }

    /**
     * Factory method creates wire\Method  objects based on class name
     * and parameters.
     *
     * @arg  string   $class       Amqp class
     * @arg  array    $args       Format: array (<Amqp method name>,
     *                                           <Assoc method/class mixed field array>,
     *                                           <method content>)
     */
    function __call ($class, $args) {
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8766);
        }
        $m = $this->myConn->constructMethod($class, $args);
        $m->setWireChannel($this->chanId);
        $m->setMaxFrameSize($this->frameMax);
        return $m;
    }

    /**
     * A wrapper for Connection->invoke() specifically for messages on
     * this channel.
     */
    function invoke (wire\Method $m) {
        if ($this->destroyed) {
            throw new \Exception("Attempting to use a destroyed channel", 8767);
        } else if (! $this->flow) {
            trigger_error("Channel is closed", E_USER_WARNING);
            return;
        } else if (is_null($tmp = $m->getWireChannel())) {
            $m->setWireChannel($this->chanId);
        } else if ($tmp != $this->chanId) {
            throw new \Exception("Method is invoked through the wrong channel", 7645);
        }

        // Do numbering of basic.publish during confirm mode
        if ($this->confirmMode && $m->amqpClass == 'basic.publish') {
            $this->confirmSeq++;
            $this->confirmSeqs[$this->confirmSeq] = $m;
        }

        return $this->myConn->invoke($m);
    }

    /**
     * Callback  from the  Connection  object for  channel frames  and
     * messages.  Only channel class methods should be delivered here.
     * @param   $meth           A channel method for this channel
     * @return  boolean         True:  Add message to internal queue for regular delivery
     *                          False: Remove message from internal queue
     */
    function handleChannelMessage (wire\Method $meth) {
        switch ($meth->amqpClass) {
        case 'channel.flow':
            $this->flow = ! $this->flow;
            if ($r = $meth->getMethodProto()->getResponses()) {
                $meth = new wire\Method($r[0], $this->chanId);
                $this->invoke($meth);
            }
            return false;
        case 'channel.close':
            $pl = $this->myConn->getProtocolLoader();
            if ($culprit = $pl('ClassFactory', 'GetMethod', array($meth->getField('class-id'),
                                                                  $meth->getField('method-id')))) {
                $culprit = $culprit->getSpecName();
            } else {
                $culprit = '(Unknown or unspecified)';
            }
            // Note: ignores the soft-error, hard-error distinction in the xml
            $errCode = $pl('ProtoConsts', 'Konstant', array($meth->getField('reply-code')));
            $eb = '';
            foreach ($meth->getFields() as $k => $v) {
                $eb .= sprintf("(%s=%s) ", $k, $v);
            }
            $tmp = $meth->getMethodProto()->getResponses();
            $closeOk = new wire\Method($tmp[0], $this->chanId);
            $em = "[channel.close] reply-code={$errCode['name']} triggered by $culprit: $eb";

            try {
                $this->myConn->invoke($closeOk);
                $em .= " Channel closed OK";
                $n = 3687;
            } catch (\Exception $e) {
                $em .= " Additionally, channel closure ack send failed";
                $n = 2435;
            }
            throw new \Exception($em, $n);
        case 'channel.close-ok':
        case 'channel.open-ok':
        case 'channel.flow-ok':
            return true;
        default:
            throw new \Exception("Received unexpected channel message: {$meth->amqpClass}", 8795);
        }
    }


    /**
     * Delivery handler for all non-channel class input messages.
     */
    function handleChannelDelivery (wire\Method $meth) {
        switch ($meth->amqpClass) {
        case 'basic.deliver':
            return $this->deliverConsumerMessage($meth);
        case 'basic.return':
            if ($this->callbackHandler) {
                $this->callbackHandler->publishReturn($meth);
            }
            return false;
        case 'basic.ack':
            $this->removeConfirmSeqs($meth, 'publishConfirm');
            return false;
        case 'basic.nack':
            $this->removeConfirmSeqs($meth, 'publishNack');
            return false;
        case 'basic.cancel':
            $this->handleConsumerCancel($meth);
            break;
        default:
            throw new \Exception("Received unexpected channel delivery:\n{$meth->amqpClass}", 87998);
        }
    }


    /**
     * Handle an  incoming consumer.cancel by  notifying the consumer,
     * removing  the local  consumer and  sending  the basic.cancel-ok
     * response.
     */
    private function handleConsumerCancel ($meth) {
        $ctag = $meth->getField('consumer-tag');
        list($cons, $status,) = $this->getConsumerAndStatus($ctag);
        if ($cons && $status == 'READY') {
            $cons->handleCancel($meth, $this); // notify
            $this->setConsumerStatus($ctag, 'CLOSED') OR
                trigger_error("Failed to set consumer status flag (2)", E_USER_WARNING); // remove
            if (! $meth->getField('no-wait')) {
                $this->invoke($this->basic('cancel-ok', array('consumer-tag', $ctag))); // respond
            }
        } else if ($cons) {
            $m = sprintf("Cancellation message delivered to closed consumer %s", $ctag);
            trigger_error($m, E_USER_WARNING);
        } else {
            $m = sprintf("Unable to load consumer for consumer cancellation %s", $ctag);
            trigger_error($m, E_USER_WARNING);
        }
    }



    /**
     * Delivers 'Consume Session'  messages to channels consumers, and
     * handles responses.
     * @param    amqphp\wire\Method   $meth      Always basic.deliver
     */
    private function deliverConsumerMessage ($meth) {
        // Look up the target consume handler and invoke the callback
        $ctag = $meth->getField('consumer-tag');
        $response = false;
        list($cons, $status, $consParams) = $this->getConsumerAndStatus($ctag);

        if ($cons && $status == 'READY') {
            $response = $cons->handleDelivery($meth, $this);
        } else if ($cons) {
            $m = sprintf("Message delivered to closed consumer %s in non-ready state %s -- reject %s",
                         $ctag, $status, $meth->getField('delivery-tag'));
            trigger_error($m, E_USER_WARNING);
            $response = CONSUMER_REJECT;
        } else {
            $m = sprintf("Unable to load consumer for delivery %s -- reject %s",
                         $ctag, $meth->getField('delivery-tag'));
            trigger_error($m, E_USER_WARNING);
            $response = CONSUMER_REJECT;
        }

        if (! $response) {
            return;
        }

        if (! is_array($response)) {
            $response = array($response);
        }
        $shouldAck = (! array_key_exists('no-ack', $consParams) || ! $consParams['no-ack']);
        foreach ($response as $resp) {
            switch ($resp) {
            case CONSUMER_ACK:
                if ($shouldAck) {
                    $this->ack($meth, CONSUMER_ACK);
                }
                break;
            case CONSUMER_DROP:
            case CONSUMER_REJECT:
                if ($shouldAck) {
                    $this->ack($meth, $resp);
                }
                break;
            case CONSUMER_CANCEL:
                $this->removeConsumerByTag($cons, $ctag);
                break;
            default:
                trigger_error("Invalid consumer response $resp - consumers must " .
                              'respond with either a single consumer flag, multiple ' .
                              'consumer flags, or an empty response', E_USER_WARNING);
            }
        }

        return false;
    }


    /**
     * Ack / Nack helper, tracks  buffered acks and triggers the flush
     * when necessary
     */
    private function ack ($meth, $action) {
        if (is_null($this->ackFlag)) {
            $this->ackFlag = $action;
        } else if ($action != $this->ackFlag) {
            // Need to flush all acks before we can start accumulating acks of a different kind.
            $this->flushAcks();
            $this->ackFlag = $action;
        }
        $this->ackHead = $meth->getField('delivery-tag');
        $this->numPendAcks++;

        if ($this->numPendAcks >= $this->ackBuffer) {
            $this->flushAcks();
        }
    }


    /**
     * Ack all buffered responses and clear the local response list.
     */
    private function flushAcks () {
        if (is_null($this->ackFlag)) {
            // Nothing to do here.
            return;
        }
        switch ($this->ackFlag) {
        case CONSUMER_ACK:
            $ack = $this->basic('ack', array('delivery-tag' => $this->ackHead,
                                             'multiple' => ($this->ackBuffer > 1)));
            $this->invoke($ack);
            break;
        case CONSUMER_REJECT:
        case CONSUMER_DROP:
            $rej = $this->basic('nack', array('delivery-tag' => $this->ackHead,
                                              'multiple' => ($this->ackBuffer > 1),
                                              'requeue' => ($this->ackFlag == CONSUMER_REJECT)));
            $this->invoke($rej);
            break;
        default:
            throw new \Exception("Internal (n)ack tracking state error", 2956);
        }
        $this->ackFlag = $this->ackHead = null;
        $this->numPendAcks = 0;
    }

    /**
     * Helper:  remove  message   sequence  record(s)  for  the  given
     * basic.{n}ack (RMQ Confirm key)
     */
    private function removeConfirmSeqs (wire\Method $meth, $event) {
        if (! $this->callbackHandler) {
            trigger_error("Received publish confirmations with no channel event handler in place", E_USER_WARNING);
            return;
        }

        $dtag = $meth->getField('delivery-tag');
        if ($meth->getField('multiple')) {
            foreach (array_keys($this->confirmSeqs) as $sk) {
                if ($sk <= $dtag) {
                    $this->callbackHandler->$event($this->confirmSeqs[$sk]);
                    unset($this->confirmSeqs[$sk]);
                }
            }
        } else {
            if (array_key_exists($dtag, $this->confirmSeqs)) {
                $this->callbackHandler->$event($this->confirmSeqs[$dtag]);
                unset($this->confirmSeqs[$dtag]);
            }
        }
    }



    /**
     * Perform  a  protocol  channel  shutdown and  remove  self  from
     * containing Connection
     */
    function shutdown () {
        if (! $this->invoke($this->channel('close', array('reply-code' => '', 'reply-text' => '')))) {
            trigger_error("Unclean channel shutdown", E_USER_WARNING);
        }
        $this->myConn->removeChannel($this);
        $this->destroyed = true;
        $this->myConn = $this->chanId = $this->ticket = null;
    }

    /**
     * Add the given consumer  to the local consumer group, optionally
     * specifying consume parameters $cParams at the same time.
     */
    function addConsumer (Consumer $cons, array $cParams=null) {
        $this->consumers[] = array($cons, false, 'READY_WAIT', $cParams);
    }


    /**
     * Called from  select loop  to see whether  this object  wants to
     * continue looping.
     * @return  boolean      True:  Request Connection stays in select loop
     *                       False: Confirm to connection it's OK to exit from loop
     */
    function canListen () {
        return $this->hasListeningConsumers() || $this->hasOutstandingConfirms();
    }

    /**
     * Send basic.cancel to cancel the given consumer subscription and
     * mark as closed internally.
     */
    function removeConsumer (Consumer $cons) {
        foreach ($this->consumers as $c) {
            if ($c[0] === $cons) {
                if ($c[2] == 'READY') {
                    $this->removeConsumerByTag($c[0], $c[1]);
                }
                return;
            }
        }
        trigger_error("Consumer does not belong to this Channel", E_USER_WARNING);
    }


    /**
     * Cancel all consumers.
     */
    function removeAllConsumers () {
        foreach ($this->consumers as $c) {
            if ($c[2] == 'READY') {
                $this->removeConsumerByTag($c[0], $c[1]);
            }
        }
    }


    private function removeConsumerByTag (Consumer $cons, $ctag) {
        list(, $cstate,) = $this->getConsumerAndStatus($ctag);
        if ($cstate == 'CLOSED') {
            trigger_error("Consumer is already removed", E_USER_WARNING);
            return;
        }
        $cnl = $this->basic('cancel', array('consumer-tag' => $ctag, 'no-wait' => false));
        $cOk = $this->invoke($cnl);
        if ($cOk->amqpClass == 'basic.cancel-ok') {
            $this->setConsumerStatus($ctag, 'CLOSED') OR
                trigger_error("Failed to set consumer status flag", E_USER_WARNING);

        } else {
            throw new \Exception("Failed to cancel consumer - bad broker response", 9768);
        }
        $cons->handleCancelOk($cOk, $this);
    }

    private function setConsumerStatus ($tag, $status) {
        foreach ($this->consumers as $k => $c) {
            if ($c[1] === $tag) {
                $this->consumers[$k][2] = $status;
                return true;
            }
        }
        return false;
    }


    private function getConsumerAndStatus ($tag) {
        foreach ($this->consumers as $c) {
            if ($c[1] == $tag) {
                return array($c[0], $c[2], $c[3]);
            }
        }
        return array(null, 'INVALID', null);
    }


    function hasListeningConsumers () {
        foreach ($this->consumers as $c) {
            if ($c[2] === 'READY') {
                return true;
            }
        }
        return false;
    }

    /** Return the consumer associated with consumer tag $t  */
    function getConsumerByTag ($t) {
        foreach ($this->consumers as $c) {
            if ($c[2] == 'READY' && $c[1] === $t) {
                return $c[0];
            }
        }
    }

    /** Return an array of all consumer tags */
    function getConsumerTags () {
        $tags = array();
        foreach ($this->consumers as $c) {
            if ($c[2] == 'READY') {
                $tags[] = $c[1];
            }
        }
        return $tags;
    }


    /**
     * Invoke  the   basic.consume  amqp  command   for  all  attached
     * consumers which are in the READY_WAIT state.
     *
     * @return  boolean         Return true if any consumers were started
     */
    function startAllConsumers () {
        if (! $this->consumers) {
            return false;
        }
        $r = false;
        foreach (array_keys($this->consumers) as $cnum) {
            if (false === $this->consumers[$cnum][1]) {
                $this->_startConsumer($cnum);
                $r = true;
            }
        }
        return $r;
    }

    /**
     * Locate consume parameters for  the given consumer and start the
     * broker-side  consume  session.   After  this, the  broker  will
     * immediately start sending messages.
     */
    private function _startConsumer ($cnum) {
        $consume = false;
        if (($consume = $this->consumers[$cnum][0]->getConsumeMethod($this)) && ! ($consume instanceof wire\Method)) {
            trigger_error("Consumer returned invalid consume method", E_USER_WARNING);
            $consume = false;
        }
        if (! $consume && is_array($this->consumers[$cnum][3])) {
            // Consume params were passed to addConsumer().
            $consume = $this->basic('consume', $this->consumers[$cnum][3]);
        }
        if (! $consume) {
            throw new \Exception("Couldn't find any consume paramters while starting consumer", 9265);
        }
        $cOk = $this->invoke($consume);
        $this->consumers[$cnum][0]->handleConsumeOk($cOk, $this);
        $this->consumers[$cnum][2] = 'READY';
        $this->consumers[$cnum][1] = $cOk->getField('consumer-tag');
        $this->consumers[$cnum][3] = $consume->getFields();
    }

    /**
     * Manually  start consuming  for the  given consumer.   Note that
     * this is normally done automatically.
     * @return       boolean        True if the consumer was actually started.
     */
    function startConsumer (Consumer $cons) {
        foreach ($this->consumers as $i => $c) {
            if ($c[0] === $cons && $c[1] === false) {
                $this->_startConsumer($i);
                return true;
            }
        }
        return false;
    }

    /**
     * Consume   Lifecycle  callback,   called  from   the  containing
     * connection  to notify  that the  connection is  no longer  in a
     * consume loop
     */
    function onSelectEnd () {
        $this->flushAcks();
        $this->consuming = false;
    }

    function isSuspended () {
        return ! $this->flow;
    }

    function toggleFlow () {
        $flow = ! $this->flow;
        $this->flow = true; // otherwise the message won't send
        $meth = $this->channel('flow', array('active' => $flow));
        $fr = $this->invoke($meth);
        $newFlow = $fr->getField('active');
        if ($newFlow != $flow) {
            trigger_error(sprintf("Flow Unexpected channel flow response, expected %d, got %d", ! $this->flow, $this->flow), E_USER_WARNING);
        }
        $this->flow = $newFlow;
    }
}
/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */






/**
 * An event handler for Channel events.
 */
interface ChannelEventHandler
{
    function publishConfirm (wire\Method $meth);

    function publishReturn (wire\Method $meth);

    function publishNack(wire\Method $meth);
}
/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */









class ConditionalExitStrategy implements ExitStrategy
{
    /** A copy of the Connection from the init callback */
    private $conn;

    function configure ($sMode) {}

    function init (Connection $conn) {
        $this->conn = $conn;
    }

    function preSelect ($prev=null) {
        if ($prev === false) {
            return false;
        }
        $hasConsumers = false;
        foreach ($this->conn->getChannels() as $chan) {
            if ($chan->canListen()) {
                $hasConsumers = true;
                break;
            }
        }

        if (! $hasConsumers) {
            return false;
        } else {
            return $prev;
        }
    }

    function complete () {
        $this->conn = null;
    }
}

/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */





const DEBUG = false;

const PROTOCOL_HEADER = "AMQP\x00\x00\x09\x01";

/** Event loop exit strategy identifiers. */
const STRAT_TIMEOUT_ABS = 1;
const STRAT_TIMEOUT_REL = 2;
const STRAT_MAXLOOPS = 3;
const STRAT_CALLBACK = 4;
const STRAT_COND = 5;



/**
 * Standard  "consumer   signals"  -   these  can  be   returned  from
 * Consumer->handleDelivery  method and  trigger the  API to  send the
 * corresponding messages.
 */
const CONSUMER_ACK = 1; // basic.ack (multiple=false)
const CONSUMER_REJECT = 2; // basic.reject (requeue=true)
const CONSUMER_DROP = 3; // basic.reject (requeue=false)
const CONSUMER_CANCEL = 4; // basic.cancel (no-wait=false)






/**
 * Wraps  a  single TCP  connection  to the  amqp  broker,  acts as  a
 * demultiplexer for many channels.   Event looping behaviours are set
 * here,   and   there    is   a   simple   single-connection   select
 * implementation.
 */
class Connection
{
    /** Default client-properties field used during connection setup */
    public static $ClientProperties = array(
        'product' => ' BraveSirRobin/amqphp',
        'version' => '0.9.3',
        'platform' => 'PHP 5.3 +',
        'copyright' => 'Copyright (c) 2010,2011,2012 Robin Harvey (harvey.robin@gmail.com)',
        'information' => 'This software is released under the terms of the GNU LGPL: http://www.gnu.org/licenses/lgpl-3.0.txt',
        'capabilities' => array('exchange_exchange_bindings' => true,
                                'consumer_cancel_notify' => true,
                                'basic.nack' => true,
                                'publisher_confirms' => true));

    /** For RMQ 2.4.0+, server capabilites are stored here, as a plain array */
    public $capabilities;

    /** List of class fields that are settable connection params */
    private static $CProps = array(
        'socketImpl', 'socketParams', 'username', 'userpass', 'vhost',
        'frameMax', 'chanMax', 'signalDispatch', 'heartbeat', 'socketFlags');

    /** Connection params */
    protected $sock; // Socket wrapper object
    protected $socketImpl = '\amqphp\Socket'; // Socket impl class name
    protected $protoImpl = 'v0_9_1'; // Protocol implementation namespace (generated code)
    private $protoLoader; // Closure, set up in getProtocolLoader()
    protected $socketParams = array('host' => 'localhost', 'port' => 5672); // Construct params for $socketImpl
    private $socketFlags;
    private $username;
    private $userpass;
    protected $vhost;
    protected $frameMax = 65536; // Negotated during setup.
    protected $chanMax = 50; // Negotated during setup.
    private $heartbeat = 0; // Negotated during setup.
    protected $signalDispatch = true;


    protected $chans = array(); // Format: array(<chan-id> => Channel)
    protected $nextChan = 1;


    /** Flag set when connection is in read blocking mode, waiting for messages */
    private $blocking = false;


    protected $unDelivered = array(); // List of undelivered messages, Format: array(<wire\Method>)
    protected $unDeliverable = array(); // List of undeliverable messages, Format: array(<wire\Method>)
    protected $incompleteMethods = array(); // List of partial messages, Format: array(<wire\Method>)
    protected $readSrc = null; // wire\Reader, used between reads when partial frames are read from the wire


    protected $connected = false; // Flag flipped after protcol connection setup is complete

    /** A set of exit strategies, forms a chain of responsibility */
    private $exStrats = array();



    function __construct (array $params = array()) {
        $this->setConnectionParams($params);
    }

    /**
     * Assoc array sets the connection parameters
     */
    function setConnectionParams (array $params) {
        foreach (self::$CProps as $pn) {
            if (isset($params[$pn])) {
                $this->$pn = $params[$pn];
            }
        }
    }


    /**
     * Return a function that loads protocol binding classes 
     */
    function getProtocolLoader () {
        if (is_null($this->protoLoader)) {
            $protoImpl = $this->protoImpl;
            $this->protoLoader = function ($class, $method, $args) use ($protoImpl) {
                $fqClass = '\\amqphp\\protocol\\' . $protoImpl . '\\' . $class;
                return call_user_func_array(array($fqClass, $method), $args);
            };
        }
        return $this->protoLoader;
    }


    /** Shutdown child channels and then the connection  */
    function shutdown () {
        if (! $this->connected) {
            trigger_error("Cannot shut a closed connection", E_USER_WARNING);
            return;
        }
        foreach (array_keys($this->chans) as $cName) {
            $this->chans[$cName]->shutdown();
        }

        $pl = $this->getProtocolLoader();
        $meth = new wire\Method($pl('ClassFactory', 'GetMethod', array('connection', 'close')));
        $meth->setField('reply-code', '');
        $meth->setField('reply-text', '');
        $meth->setField('class-id', '');
        $meth->setField('method-id', '');
        if (! $this->write($meth->toBin($pl))) {
            trigger_error("Unclean connection shutdown (1)", E_USER_WARNING);
            return;
        }
        if (! ($raw = $this->read())) {
             trigger_error("Unclean connection shutdown (2)", E_USER_WARNING);
             return;
        }

        $meth = new wire\Method();
        $meth->readConstruct(new wire\Reader($raw), $pl);
        if ($meth->amqpClass != 'connection.close-ok') {
            trigger_error("Channel protocol shudown fault", E_USER_WARNING);
        }
        $this->sock->close();
        $this->connected = false;
    }


    protected function initSocket () {
        if (! isset($this->socketImpl)) {
            throw new \Exception("No socket implementation specified", 7545);
        }
        $this->sock = new $this->socketImpl($this->socketParams, $this->socketFlags, $this->vhost);
    }


    /**
     * If not already  connected, connect to the target  broker and do
     * Amqp connection setup
     * @throws \Exception
     */
    function connect () {
        if ($this->connected) {
            trigger_error("Connection is connected already", E_USER_WARNING);
            return;
        }
        $this->initSocket();
        $this->sock->connect();
        $this->doConnectionStartup();
    }



    /**
     * Helper: perform the protocol startup procedure.
     * @throws \Exception
     */
    protected function doConnectionStartup () {
        if (! $this->write(PROTOCOL_HEADER)) {
            throw new \Exception("Connection initialisation failed (1)", 9873);
        }
        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (2)", 9874);
        }
        if (substr($raw, 0, 4) == 'AMQP' && $raw !== PROTOCOL_HEADER) {
            // Unexpected AMQP version
            throw new \Exception("Connection initialisation failed (3)", 9875);
        }
        $meth = new wire\Method();
        $pl = $this->getProtocolLoader();
        $meth->readConstruct(new wire\Reader($raw), $pl);
        if (($startF = $meth->getField('server-properties'))
            && isset($startF['capabilities'])
            && ($startF['capabilities']->getType() == 'F')) {
            // Captures RMQ 2.4.0+ capabilities
            $this->capabilities = $startF['capabilities']->getValue()->getArrayCopy();
        }

        // Expect start
        if ($meth->amqpClass == 'connection.start') {
            $resp = $meth->getMethodProto()->getResponses();
            $meth = new wire\Method($resp[0]);
        } else {
            throw new \Exception("Connection initialisation failed (5)", 9877);
        }
        $meth->setField('client-properties', new wire\Table(self::$ClientProperties));
        $meth->setField('mechanism', 'AMQPLAIN');
        $meth->setField('response', $this->getSaslResponse());
        $meth->setField('locale', 'en_US');
        // Send start-ok
        if (! ($this->write($meth->toBin($pl)))) {
            throw new \Exception("Connection initialisation failed (6)", 9878);
        }

        if (! ($raw = $this->read())) {
            throw new \Exception("Connection initialisation failed (7)", 9879);
        }
        $meth = new wire\Method();
        $meth->readConstruct(new wire\Reader($raw), $pl);

        $chanMax = $meth->getField('channel-max');
        $frameMax = $meth->getField('frame-max');

        $this->chanMax = ($chanMax < $this->chanMax) ? $chanMax : $this->chanMax;
        $this->frameMax = ($this->frameMax == 0 || $frameMax < $this->frameMax) ? $frameMax : $this->frameMax;

        // Expect tune
        if ($meth->amqpClass == 'connection.tune') {
            $resp = $meth->getMethodProto()->getResponses();
            $meth = new wire\Method($resp[0]);
        } else {
            throw new \Exception("Connection initialisation failed (9)", 9881);
        }
        $meth->setField('channel-max', $this->chanMax);
        $meth->setField('frame-max', $this->frameMax);
        $meth->setField('heartbeat', $this->heartbeat);
        // Send tune-ok
        if (! ($this->write($meth->toBin($pl)))) {
            throw new \Exception("Connection initialisation failed (10)", 9882);
        }

        // Now call connection.open
        $meth = $this->constructMethod('connection', array('open', array('virtual-host' => $this->vhost)));
        $meth = $this->invoke($meth);
        if ($meth->amqpClass != 'connection.open-ok') {
            throw new \Exception("Connection initialisation failed (13)", 9885);
        }
        $this->connected = true;
    }


    /**
     * Helper: return  the Sasl response parameter  used in connection
     * setup.
     */
    private function getSaslResponse () {
        $t = new wire\Table(array('LOGIN' => $this->username, 'PASSWORD' => $this->userpass));
        $w = new wire\Writer();
        $w->write($t, 'table');
        return substr($w->getBuffer(), 4);
    }

    /** Returns the given channel. */
    function getChannel ($num) {
        return $this->chans[$num];
    }

    /** Opens a new channel on this connection. */
    function openChannel () {
        return $this->initNewChannel(__NAMESPACE__ . '\\Channel');
    }

    /** Return all channels */
    function getChannels () {
        return $this->chans;
    }

    /** Flip internal flag the decides if pcntl_signal_dispatch() gets called in consume loop */
    function setSignalDispatch ($val) {
        $this->signalDispatch = (boolean) $val;
    }

    function getSignalDispatch () {
        return $this->signalDispatch;
    }

    function getHeartbeat () {
        return $this->heartbeat;
    }

    function removeChannel (Channel $chan) {
        if (false !== ($k = array_search($chan, $this->chans))) {
            unset($this->chans[$k]);
        } else {
            trigger_error("Channel not found", E_USER_WARNING);
        }
    }

    function getSocketId () {
        return $this->sock->getId();
    }

    /** CK = Cache Key */
    function getSocketCK () {
        return $this->sock->getCK();
    }

    function clearSocketErrors () {
        $this->sock->clearErrors();
    }


    protected function initNewChannel ($impl=null) {
        if (! $this->connected) {
            trigger_error("Connection is not connected - cannot create Channel", E_USER_WARNING);
            return null;
        }
        $newChan = $this->nextChan++;
        if ($this->chanMax > 0 && $newChan > $this->chanMax) {
            throw new \Exception("Channels are exhausted!", 23756);
        }
        $this->chans[$newChan] = is_null($impl)
            ? new Channel
            : new $impl;
        $this->chans[$newChan]->setConnection($this);
        $this->chans[$newChan]->setChanId($newChan);
        $this->chans[$newChan]->setFrameMax($this->frameMax);
        $this->chans[$newChan]->initChannel();
        return $this->chans[$newChan];
    }


    function getVHost () { return $this->vhost; }


    function getSocketImplClass () { return $this->socketImpl; }

    /**
     * Returns  the  status of  the  connection  class protocol  state
     * tracking flag.  Note: doesn't not check the underlying socket.
     */
    function isConnected () { return $this->connected; }


    /**
     * Read  all  available content  from  the  wire,  if an  error  /
     * interrupt is  detected, dispatch  signal handlers and  raise an
     * exception
     **/
    private function read () {
        $ret = $this->sock->read();
        if ($ret === false) {
            $errNo = $this->sock->lastError();
            if ($this->signalDispatch && $this->sock->selectInterrupted()) {
                pcntl_signal_dispatch();
            }
            $errStr = $this->sock->strError();
            throw new \Exception ("[1] Read block select produced an error: [$errNo] $errStr", 9963);
        }
        return $ret;
    }



    /** Low level protocol write function.  Accepts either single values or arrays of content */
    private function write ($buffs) {
        $bw = 0;
        foreach ((array) $buffs as $buff) {
            $bw += $this->sock->write($buff);
        }
        return $bw;
    }



    /**
     * Handle global connection messages.
     *  The channel number is 0 for all frames which are global to the connection (4.2.3)
     */
    private function handleConnectionMessage (wire\Method $meth) {
        if ($meth->isHeartbeat()) {
            $resp = "\x08\x00\x00\x00\x00\x00\x00\xce";
            $this->write($resp);
            return;
        }

        switch ($meth->amqpClass) {
        case 'connection.close':
            $pl = $this->getProtocolLoader();
            if ($culprit = $pl('ClassFactory', 'GetMethod', array($meth->getField('class-id'),
                                                                  $meth->getField('method-id')))) {
                $culprit = $culprit->getSpecName();
            } else {
                $culprit = '(Unknown or unspecified)';
            }
            // Note: ignores the soft-error, hard-error distinction in the xml
            $errCode = $pl('ProtoConsts', 'Konstant', array($meth->getField('reply-code')));
            $eb = '';
            foreach ($meth->getFields() as $k => $v) {
                $eb .= sprintf("(%s=%s) ", $k, $v);
            }
            $tmp = $meth->getMethodProto()->getResponses();
            $closeOk = new wire\Method($tmp[0]);
            $em = "[connection.close] reply-code={$errCode['name']} triggered by $culprit: $eb";
            if ($this->write($closeOk->toBin($pl))) {
                $em .= " Connection closed OK";
                $n = 7565;
            } else {
                $em .= " Additionally, connection closure ack send failed";
                $n = 7566;
            }
            $this->sock->close();
            throw new \Exception($em, $n);
        default:
            $this->sock->close();
            throw new \Exception(sprintf("Unexpected channel message (%s), connection closed",
                                         $meth->amqpClass), 96356);
        }
    }


    function isBlocking () { return $this->blocking; }

    function setBlocking ($b) { $this->blocking = (boolean) $b; }


    /**
     * Enter  a select  loop in  order  to receive  messages from  the
     * broker.  Use  pushExitStrategy() to append exit  strategies for
     * the  loop.   Do  not  call concurrently,  this  will  raise  an
     * exception.  Use isBlocking() to test whether select() should be
     * called.
     * @throws Exception
     */
    function select () {
        $evl = new EventLoop;
        $evl->addConnection($this);
        $evl->select();
    }

    /**
     * Set  parameters that  control  how the  connection select  loop
     * behaves, implements the following exit strategies:
     *  1)  Absolute timeout -  specify a  {usec epoch}  timeout, loop
     *  breaks after this.  See the PHP man page for microtime(false).
     *  Example: "0.025 1298152951"
     *  2) Relative timeout - same as Absolute timeout except the args
     *  are  specified relative  to microtime()  at the  start  of the
     *  select loop.  Example: "0.75 2"
     *  3) Max loops
     *  4) Conditional exit (callback)
     *  5) Conditional exit (automatic) (current impl)
     *  6) Infinite

     * @param   integer    $mode      One of the STRAT_XXX consts.
     * @param   ...                   Following 0 or more params are $mode dependant
     * @return  boolean               True if the mode was set OK
     */
    function pushExitStrategy () {
        if ($this->blocking) {
            trigger_error("Push exit strategy - cannot switch mode whilst blocking", E_USER_WARNING);
            return false;
        }
        $_args = func_get_args();
        if (! $_args) {
            trigger_error("Push exit strategy - no select parameters supplied", E_USER_WARNING);
            return false;
        }
        switch ($mode = array_shift($_args)) {
        case STRAT_TIMEOUT_ABS:
        case STRAT_TIMEOUT_REL:
            @list($epoch, $usecs) = $_args;
            $this->exStrats[] = $tmp = new TimeoutExitStrategy;
            return $tmp->configure($mode, $epoch, $usecs);
        case STRAT_MAXLOOPS:
            $this->exStrats[] = $tmp = new MaxloopExitStrategy;
            return $tmp->configure(STRAT_MAXLOOPS, array_shift($_args));
        case STRAT_CALLBACK:
            $cb = array_shift($_args);
            $this->exStrats[] = $tmp = new CallbackExitStrategy;
            return $tmp->configure(STRAT_CALLBACK, $cb, $_args);
        case STRAT_COND:
            $this->exStrats[] = $tmp = new ConditionalExitStrategy;
            return $tmp->configure(STRAT_COND, $this);
        default:
            trigger_error("Select mode - mode not found", E_USER_WARNING);
            return false;
        }
    }

    /** Remove all event loop exit strategy helpers.  */
    function clearExitStrategies () {
        $this->exStrats = array();
    }

    /**
     * Internal - proxy EventLoop "notify pre-select" signal to select
     * helper.    This   is   a  "chain   of   responsibility"-   type
     * implementation, each strategy is visited  in turn and is passed
     * the response from  the previous strategy, it has  the option to
     * accept  the current  value or  replace  it with  it's own.   By
     * default we loop forever without a select timeout.
     */
    function notifyPreSelect () {
        $r = true;
        foreach ($this->exStrats as $strat) {
            $r = $strat->preSelect($r);
        }
        return $r;
    }

    /**
     * Internal  -  proxy EventLoop  "select  init"  signal to  select
     * helper
     */
    function notifySelectInit () {
        foreach ($this->exStrats as $strat) {
            $strat->init($this);
        }
        // Notify all channels
        foreach ($this->chans as $chan) {
            $chan->startAllConsumers();
        }
    }

    /**
     * Internal - proxy EventLoop "complete" signal to select helper
     */
    function notifyComplete () {
        foreach($this->exStrats as $strat) {
            $strat->complete();
        }
        // Notify all channels
        foreach ($this->chans as $chan) {
            $chan->onSelectEnd();
        }
    }


    /**
     * Internal - used by EventLoop to instruct the connection to read
     * and deliver incoming messages.
     */
    function doSelectRead () {
        $buff = $this->sock->readAll();
        if ($buff && ($meths = $this->readMessages($buff))) {
            $this->unDelivered = array_merge($this->unDelivered, $meths);
        } else if ($buff === '') {
            $this->blocking = false;
            throw new \Exception("Empty read in blocking select loop, socket error: '{$this->sock->strError()}'", 9864);
        }
    }


    /**
     * Send the given method immediately, optionally wait for the response.
     * @arg  Method     $inMeth         The method to send
     * @arg  boolean    $noWait         Flag that prevents the default behaviour of immediately
     *                                  waiting for a response - used mainly during consume.  NOTE
     *                                  that this mechanism can also be triggered via. the use of
     *                                  an Amqp no-wait domain field set to true
     */
    function invoke (wire\Method $inMeth, $noWait=false) {
        if (! ($this->write($inMeth->toBin($this->getProtocolLoader())))) {
            throw new \Exception("Send message failed (1)", 5623);
        }
        if (! $noWait && $inMeth->getMethodProto()->getSpecResponseMethods()) {
            if ($inMeth->getMethodProto()->hasNoWaitField()) {
                foreach ($inMeth->getMethodProto()->getFields() as $f) {
                    if ($f->getSpecDomainName() == 'no-wait' && $inMeth->getField($f->getSpecFieldName())) {
                        return;
                    }
                }
            }
            while (true) {
                if (! ($buff = $this->read())) {
                    throw new \Exception(sprintf("(2) Send message failed for %s", $inMeth->amqpClass), 5624);
                }
                $meths = $this->readMessages($buff);
                foreach (array_keys($meths) as $k) {
                    $meth = $meths[$k];
                    unset($meths[$k]);
                    if ($inMeth->isResponse($meth)) {
                        if ($meths) {
                            $this->unDelivered = array_merge($this->unDelivered, $meths);
                        }
                        return $meth;
                    } else {
                        $this->unDelivered[] = $meth;
                    }
                }
            }
        }
    }


    /**
     * Convert  the  given raw  wire  content  in  to Method  objects.
     * Connection and  channel messages are  delivered immediately and
     * not returned.
     */
    private function readMessages ($buff) {
        if (is_null($this->readSrc)) {
            $src = new wire\Reader($buff);
        } else {
            $src = $this->readSrc;
            $src->append($buff);
            $this->readSrc = null;
        }

        $allMeths = array(); // Collect all method here
        $pl = $this->getProtocolLoader();
        while (true) {
            $meth = null;
            // Check to see if the content can complete any locally held incomplete messages
            if ($this->incompleteMethods) {
                foreach ($this->incompleteMethods as $im) {
                    if ($im->canReadFrom($src)) {
                        $meth = $im;
                        $rcr = $meth->readConstruct($src, $pl);
                        break;
                    }
                }
            }
            if (! $meth) {
                $meth = new wire\Method;
                $this->incompleteMethods[] = $meth;
                $rcr = $meth->readConstruct($src, $pl);
            }

            if ($meth->readConstructComplete()) {
                if (false !== ($p = array_search($meth, $this->incompleteMethods, true))) {
                    unset($this->incompleteMethods[$p]);
                }
                if ($this->connected && $meth->getWireChannel() == 0) {
                    // Deliver Connection messages immediately, but only if the connection
                    // is already set up.
                    $this->handleConnectionMessage($meth);
                } else if ($meth->getWireClassId() == 20 &&
                           ($chan = $this->chans[$meth->getWireChannel()])) {
                    // Deliver Channel messages immediately
                    $chanR = $chan->handleChannelMessage($meth);
                    if ($chanR === true) {
                        $allMeths[] = $meth;
                    }
                } else {
                    $allMeths[] = $meth;
                }
            }

            if ($rcr === wire\Method::PARTIAL_FRAME) {
                $this->readSrc = $src;
                break;
            } else if ($src->isSpent()) {
                break;
            }
        }
        return $allMeths;
    }


    function getUndeliveredMessages () {
        return $this->unDelivered;
    }


    /**
     * Deliver  all   undelivered  messages,  collect   and  send  all
     * responses  after incoming  messages are  all dealt  with. NOTE:
     * while / array_shift loop is used in case onDelivery call causes
     * more messages to be placed in local queue
     */
    function deliverAll () {
        while ($this->unDelivered) {
            $meth = array_shift($this->unDelivered);
            if (isset($this->chans[$meth->getWireChannel()])) {
                $this->chans[$meth->getWireChannel()]->handleChannelDelivery($meth);
            } else {
                trigger_error("Message delivered on unknown channel", E_USER_WARNING);
                $this->unDeliverable[] = $meth;
            }
        }
    }

    function getUndeliverableMessages ($chan) {
        $r = array();
        foreach (array_keys($this->unDeliverable) as $k) {
            if ($this->unDeliverable[$k]->getWireChannel() == $chan) {
                $r[] = $this->unDeliverable[$k];
            }
        }
        return $r;
    }

    /**
     * Remove all undeliverable messages for the given channel
     */
    function removeUndeliverableMessages ($chan) {
        foreach (array_keys($this->unDeliverable) as $k) {
            if ($this->unDeliverable[$k]->getWireChannel() == $chan) {
                unset($this->unDeliverable[$k]);
            }
        }
    }


    /**
     * Factory method creates wire\Method  objects based on class name
     * and parameters.
     *
     * @arg  string   $class       Amqp class
     * @arg  array    $_args       Format: array (<Amqp method name>,
     *                                            <Assoc method/class mixed field array>,
     *                                            <Message content>)
     * @return                     A corresponding \amqphp\wire\Method
     */
    function constructMethod ($class, $_args) {
        $method = (isset($_args[0])) ? $_args[0] : null;
        $args = (isset($_args[1])) ? $_args[1] : array();
        $content = (isset($_args[2])) ? $_args[2] : null;

        $pl = $this->getProtocolLoader();
        if (! ($cls = $pl('ClassFactory', 'GetClassByName', array($class)))) {
            throw new \Exception("Invalid Amqp class or php method", 8691);
        } else if (! ($meth = $cls->getMethodByName($method))) {
            throw new \Exception("Invalid Amqp method", 5435);
        }

        $m = new wire\Method($meth);
        foreach ($args as $k => $v) {
            $m->setField($k, $v);
        }
        $m->setContent($content);
        return $m;
    }
}

/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */





// Interface for a consumer callback handler object, based on the RMQ java on here:
// http://www.rabbitmq.com/releases/rabbitmq-java-client/v2.2.0/rabbitmq-java-client-javadoc-2.2.0/com/rabbitmq/client/Consumer.html
interface Consumer
{
    /**
     * Must handle Cancel-OK responses
     * @return void
     */
    function handleCancelOk (wire\Method $meth, Channel $chan);

    /**
     * Must  handle incoming  Cancel  messages.  This  is a  RabbitMq-
     * specific extension
     * @return void
     */
    function handleCancel (wire\Method $meth, Channel $chan);

    /**
     * Must handle Consume-OK responses
     * @return void
     */
    function handleConsumeOk (wire\Method $meth, Channel $chan);

    /**
     * Must handle message deliveries 
     * @return array    A list of \amqphp\CONSUMER_* consts, these are
     *                  sent to the broker by $chan
     */
    function handleDelivery (wire\Method $meth, Channel $chan);

    /**
     * Must handle Recovery-OK responses 
     * @return void
     */
    function handleRecoveryOk (wire\Method $meth, Channel $chan);

    /**
     * Lifecycle callback  method - calleds by  the containing channel
     * so   that   the  consumer   can   provide   it's  own   consume
     * parameters.  See:
     * http://www.rabbitmq.com/amqp-0-9-1-quickref.html#basic.consume
     * @return \amqphp\wire\Method    An amqp basic.consume method
     */
    function getConsumeMethod (Channel $chan);
}

/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */






/**
 * Use the  low level Zelect method  to allow consumers  to connect to
 * more than one exchange.
 */
class EventLoop
{
    /**
     * When the  forced heatbeat loop exit monitoring  is active, this
     * number  is used  as a  milliseconds buffer  such that  the loop
     * timeout is set to <hearbeat>  + HB_TMOBUFF.  If the loop breaks
     * on this timeout, we consider the heartbeat to be missed
     */
    const HB_TMOBUFF = 50000;

    private $cons = array();
    private static $In = false;

    private $forceExit = false;
    private $minHb = -1;

    function addConnection (Connection $conn) {
        $this->cons[$conn->getSocketId()] = $conn;
        $this->setMinHb();
    }

    private function setMinHb () {
        if ($this->cons) {
            foreach ($this->cons as $c) {
                if ((($n = $c->getHeartbeat()) > 0) && $n > $this->minHb) {
                    $this->minHb = $n;
                }
            }
        } else {
            $this->minHb = -1;
        }
    }

    function removeConnection (Connection $conn) {
        if (array_key_exists($conn->getSocketId(), $this->cons)) {
            unset($this->cons[$conn->getSocketId()]);
        }
        $this->setMinHb();
    }

    /** Flips  an   internal  flag  that  forces  the   loop  to  exit
     * immediately the next time round. */
    function forceLoopExit () {
        $this->forceExit = true;
    }

    /**
     * Go in  to a listen  loop until no  more of the  currently added
     * connections is listening.
     */
    function select () {
        $sockImpl = false;

        foreach ($this->cons as $c) {
            if ($c->isBlocking()) {
                throw new \Exception("Event loop cannot start - connection is already blocking", 3267);
            }
            if ($sockImpl === false) {
                $sockImpl = $c->getSocketImplClass();
            } else if ($sockImpl != $c->getSocketImplClass()) {
                throw new \Exception("Event loop doesn't support mixed socket implementations", 2678);
            }
            if (! $c->isConnected()) {
                throw new \Exception("Connection is not connected", 2174);
            }
        }

        // Notify that the loop begins
        foreach ($this->cons as $c) {
            $c->setBlocking(true);
            $c->notifySelectInit();
        }

        // The loop
        $started = false;
        $missedHb = 0;
        while (true) {
            // Deliver all buffered messages and collect pre-select signals.
            $tv = array();
            foreach ($this->cons as $cid => $c) {
                $c->deliverAll();
                $tv[] = array($cid, $c->notifyPreSelect());
            }

            $psr = $this->processPreSelects($tv); // Connections could be removed here.

            if (is_array($psr)) {
                list($tvSecs, $tvUsecs) = $psr;
            } else if ($psr === true) {
                $tvSecs = null;
                $tvUsecs = 0;
            } else if (is_null($psr) && empty($this->cons)) {
                // All connections have finished listening.
                if (! $started) {
                    trigger_error("Select loop not entered - no connections are listening", E_USER_WARNING);
                }
                break;
            } else {
                throw new \Exception("Unexpected PSR response", 2758);
            }

            $this->signal();

            // If the force exit flag is set, exit now - place this after the call to signal
            if ($this->forceExit) {
                trigger_error("Select loop forced exit over-rides connection looping state", E_USER_WARNING);
                $this->forceExit = false;
                break;
            }

            $started = true;
            $selectCalledAt = microtime();
            if (is_null($tvSecs)) {
                list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'),
                                                        array_keys($this->cons), null, 0);
            } else {
                list($ret, $read, $ex) = call_user_func(array($sockImpl, 'Zelekt'),
                                                        array_keys($this->cons), $tvSecs, $tvUsecs);
            }

            if ($ret === false) {
                $this->signal();
                $errNo = $errStr = array('(No specific socket exceptions found)');
                if ($ex) {
                    $errNo = $errStr = array();
                    foreach ($ex as $sock) {
                        $errNo[] = $sock->lastError();
                        $errStr[] = $sock->strError();
                    }
                }
                $eMsg = sprintf("[2] Read block select produced an error: [%s] (%s)",
                                implode(",", $errNo), implode("),(", $errStr));
                throw new \Exception ($eMsg, 9963);

            } else if ($ret > 0) {
                $missedHb = 0;
                foreach ($read as $sock) {
                    $c = $this->cons[$sock->getId()];
                    try {
                        $c->doSelectRead();
                        $c->deliverAll();
                    } catch (\Exception $e) {
                        if ($sock->lastError()) {
                            trigger_error("Exception raised on socket {$sock->getId()} during " .
                                          "event loop read (nested exception follows). Socket indicates an error, " .
                                          "close the connection immediately.  Nested exception: '{$e->getMessage()}'",
                                          E_USER_WARNING);
                            try {
                                $c->shutdown();
                            } catch (\Exception $e) {
                                trigger_error("Nested exception swallowed during emergency socket " .
                                              "shutdown: '{$e->getMessage()}'", E_USER_WARNING);
                            }
                            $this->removeConnection($c);
                        } else {
                            trigger_error("Exception raised on socket {$sock->getId()} during " .
                                          "event loop read (nested exception follows). Socket does NOT " .
                                          "indicate an error, try again.  Nested exception: '{$e->getMessage()}'", E_USER_WARNING);

                        }
                    }
                }
            } else {
                if ($this->minHb > 0) {
                    // Check to see if the empty read is due to a missed heartbeat.
                    list($stUsecs, $stSecs) = explode(' ', $selectCalledAt);
                    list($usecs, $secs) = explode(' ', microtime());
                    if (($secs + $usecs) - ($stSecs + $stUsecs) > $this->minHb) {
                        if (++$missedHb >= 2) {
                            throw new \Exception("Broker missed too many heartbeats", 2957);
                        } else {
                            trigger_error("Broker heartbeat missed from client side, one more triggers loop exit", E_USER_WARNING);
                        }
                    }
                }
            }
        } // End - the loop

        // Notify all existing connections that the loop has ended.
        foreach ($this->cons as $id => $conn) {
            $conn->notifyComplete();
            $conn->setBlocking(false);
            $this->removeConnection($conn);
        }
    }

    /**
     * Process  preSelect  responses,   remove  connections  that  are
     * complete  and  filter  out  the "soonest"  timeout.   Call  the
     * 'complete' callback for connections that get removed
     *
     * @return  mixed   True=Loop without timeout,
     *                  False=exit loop,
     *                  array(int, int)=specific timeout
     */
    private function processPreSelects (array $tvs) {
        $wins = null;
        foreach ($tvs as $tv) {
            $sid = $tv[0]; // Socket id
            $tv = $tv[1]; // Return value from preSelect()
            if ($tv === false) {
                $this->cons[$sid]->notifyComplete();
                $this->cons[$sid]->setBlocking(false);
                $this->removeConnection($this->cons[$sid]);
            } else if (is_null($wins)) {
                $wins = $tv;
            } else if ($tv === true && ! is_array($wins)) {
                $wins = true;
            } else if (is_array($tv)) {
                if ($wins === true) {
                    $wins = $tv;
                } else {
                    // Figure out which timeout is sooner and choose that one
                    switch (bccomp((string) $wins[0], (string) $tv[0])) {
                    case 0:
                        // Seconds are the same, compare millis
                        if (1 === bccomp((string) $wins[1], (string) $tv[1])) {
                            $wins = $tv;
                        }
                        break;
                    case 1;
                        // $wins second timeout is bigger
                        $wins = $tv;
                        break;
                    }
                }
            }
        }
        // Check to see if we need to alter the timeout to match a heartbeat
        if ($wins &&
            ($this->minHb > 0) &&
            ($wins === true || $wins[0] > $this->minHb ||
             ($wins[0] == $this->minHb && $wins[1] < self::HB_TMOBUFF))
            ) {
            $wins = array($this->minHb, self::HB_TMOBUFF);
        }
        return $wins;
    }

    private function signal () {
        foreach ($this->cons as $c) {
            if ($c->getSignalDispatch()) {
                pcntl_signal_dispatch();
                return;
            }
        }
    }
}


/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */






/**
 * @internal
 */
interface ExitStrategy
{
    /**
     * Called once when the  select mode is first configured, possibly
     * with other parameters
     */
    function configure ($sMode);

    /**
     * Called once  per select loop run, calculates  initial values of
     * select loop timeouts.
     */
    function init (Connection $conn);

    /**
     * Forms a "chain of responsibility" - each
     *
     * @return   mixed    True=Loop without timeout, False=exit loop, array(int, int)=specific timeout
     */
    function preSelect ($prev=null);

    /**
     * Notification that the loop has exited
     */
    function complete ();
}

/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This  library is  free  software; you  can  redistribute it  and/or
 * modify it under the terms  of the GNU Lesser General Public License
 * as published by the Free Software Foundation; either version 2.1 of
 * the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful, but
 * WITHOUT  ANY  WARRANTY;  without   even  the  implied  warranty  of
 * MERCHANTABILITY or  FITNESS FOR A PARTICULAR PURPOSE.   See the GNU
 * Lesser General Public License for more details.

 * You should  have received a copy  of the GNU  Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation,  Inc.,  51 Franklin  Street,  Fifth  Floor, Boston,  MA
 * 02110-1301 USA
 */







/**
 * This  is a  helper  class  which can  create  Amqp connections  and
 * channels  (persistent and  non-persistent), add  consumers, channel
 * event  handlers, and  call amqp  methods.   All of  the broker  and
 * client  configuration can  be  stored  in an  XML  file, and  their
 * corresponding setup be performed by a single method call.
 *
 * Note that the use of this  component is not required, you can still
 * set up  your connections / channels and  broker configuration calls
 * manually, if desired.
 */
class Factory
{
    /* Constructor flag - load XML from the given file */
    const XML_FILE = 1;

    /* Constructor flag - load XML from the given string */
    const XML_STRING = 2;

    /** Cache ReflectionClass instances, key is class name */
    private static $RC_CACHE = array();


    /* SimpleXML object, with content x-included */
    private $simp;

    /* Name of the configuration root element */
    private $rootEl;

    function __construct ($xml, $documentURI=false, $flag=self::XML_FILE) {
        $d = new \DOMDocument;

        switch ($flag) {
        case self::XML_FILE:
            if (! $d->load($xml)) {
                throw new \Exception("Failed to load factory XML", 92656);
            }
            break;
        case self::XML_STRING:
            if (! $d->loadXML($xml)) {
                throw new \Exception("Failed to load factory XML", 92656);
            }
            if ($documentURI) {
                $d->documentURI = $documentURI;
            }
            break;
        default:
            throw new \Exception("Invalid construct flag", 95637);
        }

        if (-1 === $d->xinclude()) {
            throw new \Exception("Failed to load factory XML", 92657);
        } else if (! ($this->simp = simplexml_import_dom($d))) {
            throw new \Exception("Failed to load factory XML", 92658);
        }

        switch ($tmp = strtolower((string) $this->simp->getName())) {
        case 'setup':
        case 'methods':
            $this->rootEl = $tmp;
            break;
        default:
            throw new \Exception("Unexpected Factory configuration data root element", 17893);
        }
    }


    /**
     * Run the XML instructions and return the corresponding objects /
     * responses.
     */
    function run (Channel $chan=null) {
        switch ($this->rootEl) {
        case 'setup':
            return $this->runSetupSequence();
        case 'methods':
            if (is_null($chan)) {
                throw new \Exception("Invalid factory configuration - expected a target channel", 15758);
            }
            return $this->runMethodSequence($chan, $this->simp->xpath('/methods/method'));
        }
    }


    /**
     * Helper  method -  run the  config and  return  only connections
     * (throw away method responses)
     */
    function getConnections () {
        $r = array();
        foreach ($this->run() as $res) {
            if ($res instanceof Connection) {
                $r[] = $res;
            }
        }
        return $r;
    }


    /**
     * Helper: loop the children of a set_properties element of $conf,
     * an set these as scalar key value pairs as properties of $subj
     */
    private function callProperties ($subj, $conf) {
        foreach ($conf->xpath('./set_properties/*') as $prop) {
            $pname = (string) $prop->getName();
            $pval = $this->kast($prop, $prop['k']);
            $subj->$pname = $pval;
        }
    }


    /**
     * Run the connection setup sequence
     */
    private function runSetupSequence () {
        $ret = array();

        foreach ($this->simp->connection as $conn) {
            $_chans = array();

            // Create connection and connect
            $refl = $this->getRc((string) $conn->impl);
            $_conn = $refl->newInstanceArgs($this->xmlToArray($conn->constr_args->children()));
            $this->callProperties($_conn, $conn);
            $_conn->connect();
            $ret[] = $_conn;

            // Add exit strategies, if required.
            if (count($conn->exit_strats) > 0) {
                foreach ($conn->exit_strats->strat as $strat) {
                    call_user_func_array(array($_conn, 'pushExitStrategy'), $this->xmlToArray($strat->children()));
                }
            }

            if ($_conn instanceof pers\PConnection && $_conn->getPersistenceStatus() == pers\PConnection::SOCK_REUSED) {
                // Assume that the setup is complete for existing PConnection
                // ??TODO??  Run method sequence here too?
                continue;
            }



            // Create channels and channel event handlers.
            foreach ($conn->channel as $chan) {
                $_chan = $_conn->openChannel();
                $this->callProperties($_chan, $chan);
                if (isset($chan->event_handler)) {
                    $impl = (string) $chan->event_handler->impl;
                    if (count($chan->event_handler->constr_args)) {
                        $refl = $this->getRc($impl);
                        $_evh = $refl->newInstanceArgs($this->xmlToArray($chan->event_handler->constr_args->children()));
                    } else {
                        $_evh = new $impl;
                    }
                    $_chan->setEventHandler($_evh);
                }
                $_chans[] = $_chan;
                $rMeths = $chan->xpath('.//method');

                if (count($rMeths) > 0) {
                    $ret[] = $this->runMethodSequence($_chan, $rMeths);
                }
                if (count($chan->confirm_mode) > 0 && $this->kast($chan->confirm_mode, 'boolean')) {
                    $_chan->setConfirmMode();
                }
            }


            /* Finally, set  up consumers.  This is done  last in case
               queues /  exchanges etc. need  to be set up  before the
               consumers. */
            $i = 0;
            foreach ($conn->channel as $chan) {
                $_chan = $_chans[$i++];
                foreach ($chan->consumer as $cons) {
                    $impl = (string) $cons->impl;
                    if (count($cons->constr_args)) {
                        $refl = $this->getRc($impl);
                        $_cons = $refl->newInstanceArgs($this->xmlToArray($cons->constr_args->children()));
                    } else {
                        $_cons = new $impl;
                    }
                    $this->callProperties($_cons, $cons);
                    $_chan->addConsumer($_cons);
                    if (isset($cons->autostart) && $this->kast($cons->autostart, 'boolean')) {
                        $_chan->startConsumer($_cons);
                    }
                }
            }
        }
        return $ret;
    }



    /**
     * Execute the  methods defined  in $meths against  channel $chan,
     * return the results.
     */
    private function runMethodSequence (Channel $chan, array $meths) {
        $r = array();
        // Execute whatever methods are supplied.
        foreach ($meths as $iMeth) {
            $a = $this->xmlToArray($iMeth);
            $c = $a['a_class'];
            $r[] = $chan->invoke($chan->$c($a['a_method'], $a['a_args']));
        }
        return $r;
    }



    /**
     * Perform the given cast on the given value, defaults to a string
     * cast.
     */
    private function kast ($val, $cast) {
        switch ($cast) {
        case 'string':
            return (string) $val;
        case 'bool':
        case 'boolean':
            $val = trim((string) $val);
            if ($val === '0' || strtolower($val) === 'false') {
                return false;
            } else if ($val === '1' || strtolower($val) === 'true') {
                return true;
            } else {
                trigger_error("Bad boolean cast $val - use 0/1 true/false", E_USER_WARNING);
                return true;
            }
        case 'int':
        case 'integer':
            return (int) $val;
        case 'const':
            return constant((string) $val);
        case 'eval':
            return eval((string) $val);
        default:
            trigger_error("Unknown Kast $cast", E_USER_WARNING);
            return (string) $val;
        }
    }


    /**
     * Recursively convert  an XML  structure to nested  assoc arrays.
     * For each "leaf", use the "cast" given in the @k attribute.
     */
    private function xmlToArray (\SimpleXmlElement $e) {
        $ret = array();
        foreach ($e as $c) {
            $ret[(string) $c->getName()] = (count($c) == 0)
                ? $this->kast($c, (string) $c['k'])
                : $this->xmlToArray($c);
        }
        return $ret;
    }


    /** Accessor for the local ReflectionClass cache */
    private function getRc ($class) {
        return array_key_exists($class, self::$RC_CACHE)
            ? self::$RC_CACHE[$class]
            : (self::$RC_CACHE[$class] = new \ReflectionClass($class));
    }
}
/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */







/**
 * This exit strategy counts the  number of times the underlying event
 * loop breaks and forces an exit  after a specified number of breaks.
 * NOTE: this  does _NOT_ mean the  same as a "max  number of messages
 * received"  - a  single  break of  an event  loop  can deliver  many
 * messages, or indeed deliver no message, for example if a very large
 * message is being received in many parts.
 */
class MaxloopExitStrategy implements ExitStrategy
{
    /** Config param - max loops value */
    private $maxLoops;

    /** Runtime param */
    private $nLoops;

    function configure ($sMode, $ml=null) {
        if (! (is_int($ml) || is_numeric($ml)) || $ml == 0) {
            trigger_error("Select mode - invalid maxloops params : '$ml'", E_USER_WARNING);
            return false;
        } else {
            $this->maxLoops = (int) $ml;
            return true;
        }
    }

    function init (Connection $conn) {
        $this->nLoops = 0;
    }

    function preSelect ($prev=null) {
        if ($prev === false) {
            return false;
        }
        if (++$this->nLoops > $this->maxLoops) {
            return false;
        } else {
            return $prev;
        }
    }

    function complete () {}
}

/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */







class SimpleConsumer implements Consumer
{
    protected $consumeParams;
    protected $consuming = false;

    function __construct (array $consumeParams) {
        $this->consumeParams = $consumeParams;
    }

    function handleCancelOk (wire\Method $meth, Channel $chan) { $this->consuming = false; }

    function handleCancel (wire\Method $meth, Channel $chan) { $this->consuming = false; }

    function handleConsumeOk (wire\Method $meth, Channel $chan) { $this->consuming = true; }

    function handleDelivery (wire\Method $meth, Channel $chan) {}

    function handleRecoveryOk (wire\Method $meth, Channel $chan) {} // TODO: This is unreachable - use or lose!!

    function getConsumeMethod (Channel $chan) {
        return $chan->basic('consume', $this->consumeParams);
    }
}
/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */






/**
 * Wrapper for a _single_ socket
 */
class Socket
{
    const READ_SELECT = 1;
    const WRITE_SELECT = 2;
    const READ_LENGTH = 4096;

    /** For blocking IO operations, the timeout buffer in seconds. */
    const BLOCK_TIMEOUT = 5;


    /** A store of all connected instances */
    private static $All = array();

    /** Assign each socket an ID */
    private static $Counter = 0;

    private $host;
    private $port;

    private $sock;
    private $id;
    private $vhost;
    private $connected = false;
    private static $interrupt = false;
    

    function __construct ($params, $flags, $vhost) {
        $this->host = $params['host'];
        $this->port = $params['port'];
        $this->id = ++self::$Counter;
        $this->vhost = $vhost;
    }

    function getVHost () {
        return $this->vhost;
    }

    /** Return a cache key for this socket's address */
    function getCK () {
        return sprintf("%s:%s:%s", $this->host, $this->port, md5($this->vhost));
    }

    function connect () {
        if (! ($this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
            throw new \Exception("Failed to create inet socket", 7895);
        } else if (! socket_connect($this->sock, $this->host, $this->port)) {
            throw new \Exception("Failed to connect inet socket ({$this->host}, {$this->port})", 7564);
        } else if (! socket_set_nonblock($this->sock)) {
            throw new \Exception("Failed to switch connection in to non-blocking mode.", 2357);
        }
        $this->connected = true;
        self::$All[] = $this;
    }


    /**
     * Used   only    for   compatibility   with    the   StreamSocket
     * implementation: persistent connections  aren't supported in the
     * sockets extension.
     */
    function isReusedPSock () {
        return false;
    }

    /**
     * Puts  the local  socket  in to  a  select loop  with the  given
     * timeout and returns the result
     */
    function select ($tvSec, $tvUsec = 0, $rw = self::READ_SELECT) {
        $read = $write = $ex = null;
        if ($rw & self::READ_SELECT) {
            $read = $ex = array($this->sock);
        }
        if ($rw & self::WRITE_SELECT) {
            $write = $ex = array($this->sock);
        }
        if (! $read && ! $write) {
            throw new \Exception("Select must read and/or write", 9864);
        }
        self::$interrupt = false;
        $ret = socket_select($read, $write, $ex, $tvSec, $tvUsec);
        if ($ret === false && $this->lastError() == SOCKET_EINTR) {
            self::$interrupt = true;
        }
        return $ret;
    }


    /**
     * Call select on the given stream objects
     * @param   array    $incSet       List of Socket Id values of sockets to include in the select
     * @param   array    $tvSec        socket timeout - seconds
     * @param   array    $tvUSec       socket timeout - milliseconds
     * @return  array                  array(<select return>, <Socket[] to-read>, <Socket[] errs>)
     */
    static function Zelekt (array $incSet, $tvSec, $tvUsec) {
        $write = null;
        $read = $all = array();
        foreach (self::$All as $i => $o) {
            if (in_array($o->id, $incSet)) {
                $read[$i] = $all[$i] = $o->sock;
            }
        }
        $ex = $read;
        $ret = false;
        if ($read) {
            $ret = socket_select($read, $write, $ex, $tvSec, $tvUsec);
        }
        if ($ret === false && socket_last_error() == SOCKET_EINTR) {
            self::$interrupt = true;
            return false;
        }
        $_read = $_ex = array();
        foreach ($read as $sock) {
            if (false !== ($key = array_search($sock, $all, true))) {
                $_read[] = self::$All[$key];
            }
        }
        foreach ($ex as $k => $sock) {
            if (false !== ($key = array_search($sock, $all, true))) {
                $_ex[] = self::$All[$key];
            }
        }
        return array($ret, $_read, $_ex);
    }



    /**
     * Return true if the last call to select was interrupted
     */
    function selectInterrupted () {
        return self::$interrupt;
    }

    /**
     * Blocking version of readAll()
     */
    function read () {
        $buff = '';
        $select = $this->select(self::BLOCK_TIMEOUT);
        if ($select === false) {
            return false;
        } else if ($select > 0) {
            $buff = $this->readAll();
        }
        return $buff;
    }


    /**
     * Wrapper for the socket_last_error  function - return codes seem
     * to be system- dependant
     */
    function lastError () {
        return socket_last_error($this->sock);
    }

    /**
     * Clear errors on the local socket
     */
    function clearErrors () {
        socket_clear_error($this->sock);
    }

    /**
     * Simple wrapper for socket_strerror
     */
    function strError () {
        return socket_strerror($this->lastError());
    }

    /**
     * Performs  a non-blocking read  and consumes  all data  from the
     * local socket, returning the contents as a string
     */
    function readAll ($readLen = self::READ_LENGTH) {
        $buff = '';
        while (@socket_recv($this->sock, $tmp, $readLen, MSG_DONTWAIT)) {
            $buff .= $tmp;
        }
        if (DEBUG) {
            echo "\n<read>\n";
            echo wire\Hexdump::hexdump($buff);
        }
        return $buff;
    }

    /**
     * Performs a blocking write
     */
    function write ($buff) {
        if (! $this->select(self::BLOCK_TIMEOUT, 0, self::WRITE_SELECT)) {
            trigger_error('Socket select failed for write (socket err: "' . $this->strError() . ')',
                          E_USER_WARNING);
            return 0;
        }
        $bw = 0;
        $contentLength = strlen($buff);
        while (true) {
            if (DEBUG) {
                echo "\n<write>\n";
                echo wire\Hexdump::hexdump($buff);
            }
            if (($tmp = socket_write($this->sock, $buff)) === false) {
                throw new \Exception(sprintf("Socket write failed: %s",
                                             $this->strError()), 7854);
            }
            $bw += $tmp;
            if ($bw < $contentLength) {
                $buff = substr($buff, $bw);
            } else {
                break;
            }
        }
        return $bw;
    }

    function close () {
        $this->connected = false;
        socket_close($this->sock);
        $this->detach();
    }

    /** Removes self from Static store */
    private function detach () {
        if (false !== ($k = array_search($this, self::$All))) {
            unset(self::$All[$k]);
        }
    }

    function getId () {
        return $this->id;
    }
}

/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */






/**
 * TODO: Why are socket flags  stored as strings?  Factory impl should
 * make it possible to use the actual values at this level
 */
class StreamSocket
{
    const READ_SELECT = 1;
    const WRITE_SELECT = 2;
    const READ_LENGTH = 4096;

    /** For blocking IO operations, the timeout buffer in seconds. */
    const BLOCK_TIMEOUT = 5;

    /** A store of all connected instances */
    private static $All = array();

    private static $Counter = 0;

    /** Target broker address details */
    private $host, $port, $vhost;

    /** Internal state tracking variables */
    private $id, $connected, $interrupt = false;

    /** Flags  that are  passed to  stream_socket_client(),  stored as
     * strings. not the actual constant! */
    private $flags;

    /** Connection  startup  file pointer  -  set  during the  connect
     * routine, will be > 0 for re-used persistent connections */
    private $stfp;

    /** Bitmask of 1=read error, 2=write  error - set in case fread or
     * fwrite calls return false */
    private $errFlag = 0;

    function __construct ($params, $flags, $vhost) {
        $this->url = $params['url'];
        $this->context = isset($params['context']) ? $params['context'] : array();
        $this->flags = $flags ? $flags : array();
        $this->id = ++self::$Counter;
        $this->vhost = $vhost;
    }

    function getVHost () {
        return $this->vhost;
    }

    /** Return a cache key for this socket's address */
    function getCK () {
        return md5(sprintf("%s:%s:%s", $this->url, $this->getFlags(), $this->vhost));
    }


    private function getFlags () {
        $flags = STREAM_CLIENT_CONNECT;
        foreach ($this->flags as $f) {
            $flags |= constant($f);
        }
        return $flags;
    }


    /**
     * Connect  to  the  given  URL  with the  given  flags.   If  the
     * connection is  persistent, check  that the stream  socket isn't
     * shared between this and another StreamSocket object
     * @throws \Exception
     */
    function connect () {
        $context = stream_context_create($this->context);
        $flags = $this->getFlags();

        $this->sock = stream_socket_client($this->url, $errno, $errstr, 
                                           ini_get("default_socket_timeout"), 
                                           $flags, $context);

        $this->stfp = ftell($this->sock);

        if (! $this->sock) {
            throw new \Exception("Failed to connect stream socket {$this->url}, ($errno, $errstr): flags $flags", 7568);
        } else if (($flags & STREAM_CLIENT_PERSISTENT) && $this->stfp > 0) {
            foreach (self::$All as $sock) {
                if ($sock !== $this && $sock->getCK() == $this->getCK()) {
                    /* TODO: Investigate whether mixing persistent and
                     * non-persistent connections to  the same URL can
                     * provoke errors. */
                    $this->sock = null;
                    throw new \Exception(sprintf("Stream socket connection created a new wrapper object for " .
                                                 "an existing persistent connection on URL %s", $this->url), 8164);
                }
            }
        }
        if (! stream_set_blocking($this->sock, 0)) {
            throw new \Exception("Failed to place stream connection in non-blocking mode", 2795);
        }
        $this->connected = true;
        self::$All[] = $this;
    }

    /**
     * Use tell to figure out if  the socket has been newly created or
     * if it's a persistent socket which has been re-used.
     */
    function isReusedPSock () {
        return ($this->stfp > 0);
    }

    /**
     * Return the  ftell() value  that was recorded  immediately after
     * the underlying connection was opened.
     */
    function getConnectionStartFP () {
        return $this->stfp;
    }


    /**
     * Call ftell on the underlying stream and return the result
     */
    function tell () {
        return ftell($this->sock);
    }


    /**
     * A wrapper for the stream_socket function.
     */
    function select ($tvSec, $tvUsec = 0, $rw = self::READ_SELECT) {
        $read = $write = $ex = null;
        if ($rw & self::READ_SELECT) {
            $read = $ex = array($this->sock);
        }
        if ($rw & self::WRITE_SELECT) {
            $write = array($this->sock);
        }
        if (! $read && ! $write) {
            throw new \Exception("Select must read and/or write", 9864);
        }
        $this->interrupt = false;
        $ret = stream_select($read, $write, $ex, $tvSec, $tvUsec);
        if ($ret === false) {
            // A bit of an assumption here, but I can't see any more reliable method to
            // detect an interrupt using the streams API (unlike SOCKET_EINTR with the
            // sockets API)
            $this->interrupt = true;
        }
        return $ret;
    }

    /**
     * Call select on the given stream objects
     * @param   array    $incSet       List of Socket Id values of sockets to include in the select
     * @param   array    $tvSec        socket timeout - seconds
     * @param   array    $tvUSec       socket timeout - milliseconds
     * @return  array                  array(<select return>, <Socket[] to-read>, <Socket[] errs>)
     */
    static function Zelekt (array $incSet, $tvSec, $tvUsec) {
        $write = null;
        $read = $all = array();
        foreach (self::$All as $i => $o) {
            if (in_array($o->id, $incSet)) {
                $read[$i] = $all[$i] = $o->sock;
            }
        }
        $ex = $read;
        $ret = false;
        if ($read) {
            $ret = stream_select($read, $write, $ex, $tvSec, $tvUsec);
        }
        if ($ret === false) {
            // A bit of an assumption here, but I can't see any more reliable method to
            // detect an interrupt using the streams API (unlike SOCKET_EINTR with the
            // sockets API)
            return false;
        }


        $_read = $_ex = array();
        foreach ($read as $sock) {
            if (false !== ($key = array_search($sock, $all, true))) {
                $_read[] = self::$All[$key];
            }
        }
        foreach ($ex as $k => $sock) {
            if (false !== ($key = array_search($sock, $all, true))) {
                $_ex[] = self::$All[$key];
            }
        }
        return array($ret, $_read, $_ex);
    }


    /** Return true if the last call to select was interrupted */
    function selectInterrupted () {
        return $this->interrupt;
    }

    /**
     * Emulate  the socket_last_error() function  by keeping  track of
     * FALSE  values returned from  fread and  fwrite; here  we simply
     * return the local tracking bitmask
     */
    function lastError () {
        return $this->errFlag;
    }

    /**
     * Reset the error flag
     */
    function clearErrors () {
        $this->errFlag = 0;
    }

    /**
     * Emulate  the socket_strerror() function  by returning  a string
     * describing the current error state of the socket
     */
    function strError () {
        switch ($this->errFlag) {
        case 1:
            return "Read error detected";
        case 2:
            return "Write error detected";
        case 3:
            return "Read and write errors detected";
        default:
            return "No error detected";
        }
    }

    /**
     * Performs  a non-blocking read  and consumes  all data  from the
     * local socket, returning the contents as a string
     */
    function readAll ($readLen = self::READ_LENGTH) {
        $buff = '';
        do {
            $buff .= $chk = fread($this->sock, $readLen);
            $smd = stream_get_meta_data($this->sock);
            $readLen = min($smd['unread_bytes'], $readLen);
        } while ($chk !== false && $smd['unread_bytes'] > 0);
        if (! $chk) {
            trigger_error("Stream fread returned false", E_USER_WARNING);
            $this->errFlag |= 1;
        }
        if (DEBUG) {
            echo "\n<read>\n";
            echo wire\Hexdump::hexdump($buff);
        }
        return $buff;
    }

    /**
     * Blocking version of readAll()
     */
    function read () {
        $buff = '';
        $select = $this->select(self::BLOCK_TIMEOUT);
        if ($select === false) {
            return false;
        } else if ($select > 0) {
            $buff = $this->readAll();
        }
        return $buff;
    }


    /**
     * Return the number of unread bytes, or false
     * @return  mixed    Int = number of bytes, False = error
     */
    function getUnreadBytes () {
        return ($smd = stream_get_meta_data($this->sock))
            ? $smd['unread_bytes']
            : false;
    }


    function eof () {
        return feof($this->sock);
    }


    function write ($buff) {
        if (! $this->select(self::BLOCK_TIMEOUT, 0, self::WRITE_SELECT)) {
            trigger_error('StreamSocket select failed for write (stream socket err: "' . $this->strError() . ')',
                          E_USER_WARNING);
            return 0;
        }

        $bw = 0;
        $contentLength = strlen($buff);
        if ($contentLength == 0) {
            return 0;
        }
        while (true) {
            if (DEBUG) {
                echo "\n<write>\n";
                echo wire\Hexdump::hexdump($buff);
            }
            if (($tmp = fwrite($this->sock, $buff)) === false) {
                $this->errFlag |= 2;
                throw new \Exception(sprintf("\nStream write failed (error): %s\n",
                                             $this->strError()), 7854);
            } else if ($tmp === 0) {
                throw new \Exception(sprintf("\nStream write failed (zero bytes written): %s\n",
                                             $this->strError()), 7855);
            }
            $bw += $tmp;
            if ($bw < $contentLength) {
                $buff = substr($buff, $bw);
            } else {
                break;
            }
        }
        fflush($this->sock);
        return $bw;
    }

    function close () {
        $this->connected = false;
        fclose($this->sock);
        $this->detach();
    }

    /** Removes self from Static store */
    private function detach () {
        if (false !== ($k = array_search($this, self::$All))) {
            unset(self::$All[$k]);
        }
    }

    function getId () {
        return $this->id;
    }
}

/**
 *
 * Copyright (C) 2010, 2011, 2012  Robin Harvey (harvey.robin@gmail.com)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.

 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */








/**
 * This strategy can be used to set a timeout for the event loop.
 */
class TimeoutExitStrategy implements ExitStrategy
{
    /** Config param, one of STRAT_TIMEOUT_ABS or STRAT_TIMEOUT_REL */
    private $toStyle;

    /** Config param */
    private $secs;

    /** Config / Runtime param */
    private $usecs;

    /** Runtime param */
    private $epoch;

    /**
     * @param   integer     $sMode      The select mode const that was passed to pushExitStrategy
     * @param   string      $secs       The configured seconds timeout value
     * @param   string      $usecs      the configured millisecond timeout value (1 millionth of a second)
     */
    function configure ($sMode, $secs=null, $usecs=null) {
        $this->toStyle = $sMode;
        $this->secs = (string) $secs;
        $this->usecs = (string) $usecs;
        return true;
    }

    function init (Connection $conn) {
        if ($this->toStyle == STRAT_TIMEOUT_REL) {
            list($uSecs, $epoch) = explode(' ', microtime());
            $uSecs = bcmul($uSecs, '1000000');
            $this->usecs = bcadd($this->usecs, $uSecs);
            $this->epoch = bcadd($this->secs, $epoch);
            if (! (bccomp($this->usecs, '1000000') < 0)) {
                $this->epoch = bcadd('1', $this->epoch);
                $this->usecs = bcsub($this->usecs, '1000000');
            }
        } else {
            $this->epoch = $this->secs;
        }
    }

    /** Return a timeout spec */
    function preSelect ($prev=null) {
        if ($prev === false) {
            // Don't override previous handlers if they want to exit.
            return false;
        }

        list($uSecs, $epoch) = explode(' ', microtime());
        $epDiff = bccomp($epoch, $this->epoch);
        if ($epDiff == 1) {
            //$epoch is bigger
            return false;
        }
        $uSecs = bcmul($uSecs, '1000000');
        if ($epDiff == 0 && bccomp($uSecs, $this->usecs) >= 0) {
            // $usecs is bigger
            return false;
        }

        // Calculate select blockout values that expire at the same as the target exit time
        $udiff = bcsub($this->usecs, $uSecs);
        if (substr($udiff, 0, 1) == '-') {
            $blockTmSecs = (int) bcsub($this->epoch, $epoch) - 1;
            $udiff = bcadd($udiff, '1000000');
        } else {
            $blockTmSecs = (int) bcsub($this->epoch, $epoch);
        }

        // Return the nearest timeout.
        if (is_array($prev) && ($prev[0] < $blockTmSecs || ($prev[0] == $blockTmSecs && $prev[1] < $udiff))) {
            return $prev;
        } else {
            return array($blockTmSecs, $udiff);
        }
    }

    function complete () {}
}
