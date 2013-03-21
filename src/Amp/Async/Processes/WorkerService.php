<?php

namespace Amp\Async\Processes;

use Amp\Async\ProcedureException;

class WorkerService {
    
    private $parser;
    private $writer;
    private $buffer;
    
    function __construct(FrameParser $parser, FrameWriter $writer) {
        $this->parser = $parser;
        $this->writer = $writer;
    }
    
    function onReadable() {
        if (!$frameArr = $this->parser->parse()) {
            return;
        }
        
        list($isFin, $rsv, $opcode, $payload) = $frameArr;
        
        if ($opcode == Frame::OP_CLOSE) {
            return $this->close();
        }
        
        $this->buffer .= $payload;
        
        if ($isFin) {
            
            list($procedure, $args) = unserialize($this->buffer);
            
            try {
                $result = serialize(call_user_func_array($procedure, $args));
                $opcode = Frame::OP_DATA;
            } catch (\Exception $e) {
                $result = new ProcedureException(
                    "Uncaught exception encountered while invoking {$procedure}",
                    NULL,
                    $e
                );
                $opcode = Frame::OP_ERROR;
            }
            
            $this->buffer = '';
            
            $frame = new Frame($fin = 1, $rsv = 0, $opcode, $result);
            
            if (!$this->writer->write($frame)) {
                while (!$this->writer->write());
            }
        }
    }
    
    function close() {
        die;
    }
    
}

