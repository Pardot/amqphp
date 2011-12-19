<?php
 namespace amqphp\persistent; class FilePersistenceHelper implements PersistenceHelper { const TEMP_DIR = '/tmp'; private $data; private $uk; private $tmpDir = self::TEMP_DIR; function setUrlKey ($k) { if (is_null($k)) { throw new \Exception("Url key cannot be null", 8260); } $this->uk = $k; } function setTmpDir ($tmpDir) { $this->tmpDir = $tmpDir; } function getData () { return $this->data; } function setData ($data) { $this->data = $data; } function getTmpFile () { if (is_null($this->uk)) { throw new \Exception("Url key cannot be null", 8261); } return sprintf('%s%sapc.amqphp.%s.%s', $this->tmpDir, DIRECTORY_SEPARATOR, getmypid(), $this->uk); } function save () { return file_put_contents($this->getTmpFile(), (string) $this->data); } function load () { $this->data = file_get_contents($this->getTmpFile()); return ($this->data !== false); } function destroy () { return @unlink($this->getTmpFile()); } }