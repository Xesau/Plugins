<?php

namespace Xesau\Plugins;

use InvalidArgumentException;

class Plugin {
    
    private $manager;
    
    private $author;
    private $name;
    private $version;
    
    private $otherFields;
    
    public function __construct(PluginManager $manager, $key, $author, $name, $version = null, array $otherFields = []) {
        if ($manager->verifyObjectKey($key)) {
            $this->manager = $manager;
            
            $this->author = $author;
            $this->name = $name;
            $this->version = $version;
            $this->enabled = false;
            
            $this->otherFields = $otherFields;
        } else {
            throw new InvalidArgumentException('Plugin objects may only be instantiated by a PluginManager.');
        }
    }
    
    public function getIdentifier() {
        return $this->author .'.'. $this->name;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getAuthor() {
        return $this->author;
    }

    public function getVersion() {
        return $this->version;
    }
    
    public function getFields() {
        return $this->otherFields;
    }
    
    public function getManager() {
        return $this->manager;
    }
    
}
