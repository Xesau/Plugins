<?php

namespace Xesau\Plugins;

class PluginsLoadingEvent implements Event {
    
    private $manager;
    
    public function __construct(PluginManager $manager) {
        $this->manager = $manager;
    }
    
    public function getPluginManager() {
        return $this->manager;
    }
    
    public function getPlugins() {
        return $this->pluginManager->getPlugins();
    }
    
    function getName() {
        return 'plugins.loading';
    }
}
