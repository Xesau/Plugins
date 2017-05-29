# Plugins
A plugin library for PHP

## Example usage
The code provided serves as an example to demonstrate the workings of this library.

### Loading plugins
To load plugins, you need an instance of Xesau\Plugins\PluginManager. The constructor needs at least one string parameter, which is the path to the folder with the plugins. In a second parameter, you can pass a string[] with the names of the plugins that should be loaded.

    <?php

    //
    // LOADING PLUGINS
    //
    $manager = new PluginManager('/folder/with/plugins');
    $manager->loadPlugins();

    ?>

### Adding functionality
To let plugins offer extra functionality, you need a way to let them intervene in the program logic. This is using events. Events always have a `getName(): string` function, and that same name is used in by plugins for the PluginManager.listen function, outlined in the next section.

This is an example class for a UserLoginEvent, which gets thrown whenever a user tries to login.

    <?php

    //
    // DEFINING EVENT CLASS
    //
    class UserLoginEvent implements Event {

        public function getName() {
            return 'user.login';
        }

        private $user;
        private $ip;

        public function __construct(User $user, $ip) {
            $this->user = $user;
            $this->ip = $ip;
        }

        public function getUser() {
            return $this->user;
        }

        public function getIP() {
            return $this->ip;
        }
    

    }
    
    ?>
    
To implement it into the program, find a spot where you can plugins to be able to intervene. For our example, that is withing the user login process.
    
    <?php
    
    //
    // USING PLUGIN EVENTS
    //
    $event = new UserLoginEvent($user, $ip);
    $continue = $manager->call($event, true); // true --> cancellable

    if ($continue) {
        return true;
    } else {
        throw new LoginFailedException('blocked_by_plugn');
    }
    
    ?>

### Example plugin
This is an example plugin. Whenever the `user.login` event is fired, it loads the banned IPs from somewhere, and then it checks if the login IP is in the list of banned IPs. If so, it returns `false` to cancel the event (because the event was called with `true` in the `$cancellable` parameter.

    <?php
    
    //
    // PLUGIN
    //
    
    $plugin = $this->createPlugin('Xesau', 'IPBan', '2.0');
    
    if ($plugin !== false) {

        $this->listen('user.login', function(UserLoginEvent $e) {
            global $settings
            $ips = $settings[$plugin->getIdentifier()]['banned_ips'];
            
            if (in_array($e->getIP(), $ips))
                return false; // cancel
            return true;
        }
        
    }

    ?>
