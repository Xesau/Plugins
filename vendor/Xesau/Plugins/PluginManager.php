<?php

namespace Xesau\Plugins;

use Exception,
    InvalidArgumentException;

class PluginManager {
    
    /**
     * @var string $pluginDir     The directory containing all the plugin files.
     * @var string $fileExtension The extension a file must have to be considered a plugin.
     */
    private $pluginDir;
    private $fileExtension;
    
    /**
     * @var string[] $loadingQueue     The file names of the plugins to load.
     * @var bool     $hasCreatedPlugin The plugin that has just been created.
     * @var int      $pluginInitKey  The a random number to ensure only PluginManagers can create instances of the Plugin class.
     */
    private $loadingQueue;
    private $hasCreatedPlugin = false;
    private $pluginInitKey = null;
    
    /**
     * @var array[]          $plugins   The plugins that are currently loaded.
     * @var callable[string] $listeners The event listeners.
     */
    private $plugins;
    private $listeners;
    
    /**
     * Initiates a new Plugin Manager object
     *
     * @param string $pluginDir The directory where the plugin files are stored.
     * @param string $fileExtension The file extension for all the files that should be regarded plugins.
     */
    public function __construct($pluginDir, $fileExtension = 'php') {
        // Set config values
        $this->pluginDir = realpath($pluginDir);
        $this->fileExtension = (string)$fileExtension;
        
        // Initiate maps
        $this->plugins = [];
        $this->listeners = [];
    }
    
    /**
     * Registers an event listener
     *
     * @param string $eventName The name of the event, for example "plugins.loading".
     * @param callable $handler The Event handler, executed when the event is called.
     * @return void
     */
    public function listen($eventName, callable $handler) {
        // If there is no listeners array for this event yet
        if (!isset($this->listeners[$eventName])) {
            // Create one
            $this->listeners[$eventName] = [];
        }
        
        // If the given listener is not registered yet for this event
        if (!in_array($handler, $this->listeners[$eventName])) {
            // Add the listener to the array
            $this->listeners[$eventName][] = $handler;
        
        // If the listener was already registered
        } else {
            // Throw an error
            throw new InvalidArgumentException('The given handler was already registered for event '. $eventName);
        }
    }
    
    /**
     * Loads all plugins in the plugin directory.
     *
     * @param string[]|null $enabledPlugins The identifiers (string "Author.PluginName") of the plugins to load.
     */
    public function loadPlugins($enabledPlugins = null) {
        // Reset the loading queue
        $this->loadingQueue = [];
        
        // Search for plugins in the plugin directory
        $files = glob($this->pluginDir . DIRECTORY_SEPARATOR .'*.'. $this->fileExtension);
        foreach($files as $file) {
            // If the plugin file is readable
            if (is_readable($file)) {
                // Get the base name (and thus identifier) of the plugin file
                // (/plugins/Xesau.TestPlugin.php --> Xesau.TestPlugin)
                $pluginIdentifier = basename($file, '.'. $this->fileExtension);
                
                // Add the plugin name to the loading queue with value true (so it is still in the queue)
                $this->loadingQueue[$pluginIdentifier] = true;
            }
        }
        
        // If an array of enabled plugins is provided, don't load the plugins not in the array
        if (is_array($enabledPlugins)) {
            // For every plugin in the queue ...
            foreach($this->loadingQueue as $pluginIdentifier => $load) {
                // ... that is not in the array
                if (!in_array($pluginIdentifier, $enabledPlugins)) {
                    // Remove the plugin from the queue
                    unset($this->loadingQueue[$pluginIdentifier]);
                }
            }
        }
        
        // Load all the plugins that remain in the loading queue
        foreach($this->loadingQueue as $pluginIdentifier => &$mustBeLoaded) {
            if ($mustBeLoaded === true) {
                $mustBeLoaded = false;
                self::loadPlugin($pluginIdentifier);
            }
        }
        
        // Tell the plugins to load their configurations etc.
        $this->call(new PluginsLoadingEvent($this));
    }
    
    /**
     * Registers a plugin
     *
     * @param string $author The author of this plugin
     * @param string $name   The name of this plugin
     * @param mixed  $version The version of this plugin
     * @param mixed[string] $otherFields
     * @return Plugin|false The plugin object, or false if such a plugin is already loaded.
     */
    public function createPlugin($author, $name, $version = null, array $otherFields = []) {
        if (isset($this->plugins[$author .'.'. $name]))
            return false;
        
        // Set initiation key to make sure no Plugin instance is created without the PluginManager knowing of it
        $this->pluginInitKey = $k = mt_rand();
        
        // Create the plugin
        $plugin = new Plugin($this, $k, $author, $name, $version, $otherFields);
        $this->hasCreatedPlugin = true;
        
        // Reset the initiation key
        $this->pluginInitKey = null;
        
        
        // Add the plugin to the map
        $this->plugins[$plugin->getIdentifier()] = $plugin;
        return $plugin;
    }
    
    /**
     * Gets the IDs of all the registered plugins
     */
    public function getPluginIDs() {
        return array_keys($this->plugins);
    }
    
    /**
     * Gets the Plugin object for the plugin with the given identifier.
     * 
     * @param string $pluginIdentifier The plugin identifier
     * @return Plugin The Plugin object
     * @throws InvalidArgumentException When there is no plugin with this identifier.
     */
    public function getPlugin($pluginIdentifier) {
        if ($this->hasPlugin($pluginIdentifier)) {
            return $this->plugins[$pluginIdentifier];
        } else  {
            throw new InvalidArgumentException('PluginManager->getVersion: $pluginIdentifier must be a valid identifier. Please use ->hasPlugin to verify.');
        }
    }
    
    /**
     * Gets a plugins data
     *
     * @param string $pluginIdentifier The plugin identifier
     * @return array The data
     * @throws InvalidArgumentException When there is no plugin with this identifier.
     */
    public function getPluginData($pluginIdentifier) {
        if ($this->hasPlugin($pluginIdentifier)) {
            $plugin = $this->plugins[$pluginIdentifier];
            return [
                'name' => $plugin->getName(),
                'author' => $plugin->getAuthor(),
                'version' => $plugin->getVersion(),
            ] + $plugin->getFields();
        } else {
            throw new InvalidArgumentException('PluginManager->getVersion: $pluginIdentifier must be a valid identifier. Please use ->hasPlugin to verify.');
        }
    }
    
    /**
     * Checks if a plugin with this identifier has been loaded.
     *
     * @param string $pluginIdentifier The idenitifer
     * @return bool Whether the plugin has been loaded.
     */
    public function hasPlugin($pluginIdentifier) {
        // Check whether the plugin map has a key with $pluginIdentifier
        return array_key_exists($pluginIdentifier, $this->plugins);
    }
    
    /**
     * Internal function to load a plugin from the plugin directory
     */
    private function loadPlugin($pluginIdentifier) {
        // Reset $this->hasCreatedPlugin
        $this->hasCreatedPlugin = false;
        
        // Include the plugin file
        include_once $this->pluginDir . DIRECTORY_SEPARATOR .$pluginIdentifier .'.'. $this->fileExtension;
        
        // If pluginInitKey is 
        if ($this->hasCreatedPlugin == false) {
            throw new Exception('Plugin details not set with PluginManager->createPlugin ('. $pluginIdentifier .').');
        }
        
        // Reset $this->hasCreatedPlugin
        $this->hasCreatedPlugin = false;
    }
    
    /**
     * Load a plugin before another because it another plugin depends on it
     *
     * @param string $pluginIdentifier The name of the plugin this plugin depends on
     * @param bool   $hard       If true, an error is thrown when the dependency could not be found.
     * @return void
     */
    public function depends($pluginIdentifier, $hard = true) {
        // If the dependency is already loaded
        if (isset($this->loadingQueue[$pluginIdentifier])
         && $this->loadingQueue[$pluginIdentifier] === false) {
            // Continue without loading anything new
            return;
        }
        
        // Check if ->createPlugin has been called.
        if ($this->hasCreatedPlugin == false) {
            throw new Exception('Cannot load dependency ('. $pluginIdentifier .') before plugin details are set with PluginManager->createPlugin.');
        }
        
        // The dependency is not yet loaded.
        // Check the loading queue to see if the plugin was found in the plugin directory
        foreach($this->loadingQueue as $possiblePluginIdentifier => $shouldBeLoaded) {
            // If the dependency would be found
            if ($pluginIdentifier == $possiblePluginIdentifier) {
                // Remove the dependency from the loading queue and load it.
                $this->loadingQueue[$possiblePluginIdentifier] = false;
                
                $this->loadPlugin($possiblePluginIdentifier);
                $this->hasCreatedPlugin = true;
                return;
            }
        }
        
        // If the dependency is a hard dependency and was not found, throw an error.
        if ($hard === true) {
            throw new Exception('Could not load dependency '. $pluginIdentifier .'.');
        }
    }
    
    /**
     * Call an event
     *
     * @param Event $event      The event
     * @param bool  $cancelable Whether a FALSE-value can cancel the event.
     * @return bool
     */
    public function call(Event $event, $cancelable = false) {
        // If there are listeners registered for this event
        if (isset($this->listeners[$event->getName()])) {
            // Call every listener until one returns FALSE
            foreach($this->listeners[$event->getName()] as $l) {
                if (false === $l($event) && $cancelable === true)
                    return false;
            }
        }
        return true;
    }
    
    /**
     * Verifies an object key (for the Plugin class)
     *
     * @param int $key The key
     * @return bool Whether the key is valid
     */
    public function verifyObjectKey($key) {
        // If the key matches the pluginInitKey for this manager
        if ($this->pluginInitKey == $key) {   
            // Destroy the key and return true
            $this->pluginInitKey = null;
            return true;
        
        // If it doesn't
        } else {
            // Return false
            return false;
        }
    }
    
}
