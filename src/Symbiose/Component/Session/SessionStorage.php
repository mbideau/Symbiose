<?php
namespace Symbiose\Component\Session;

use Symfony\Component\HttpFoundation\SessionStorage\PdoSessionStorage,
	Symfony\Component\Finder\Finder
;

class SessionStorage
	extends PdoSessionStorage
{
	/**
     * Overwrite the constructor to load proxies classes before starting the session
     * It is a know "bug" of PHP that makes object unserialised getting a __PHP_Incomplete_Class class
     * @param string $proxiesDir The directory where the database's proxies are stored
     * @throws \InvalidArgumentException When "db_table" option is not provided
     */
    public function __construct(\PDO $db, $options = null, $proxiesDir = null)
    {
        // if proxies directory is not empty
    	if(!empty($proxiesDir) && is_dir($proxiesDir)) {
	    	$finder = new Finder();
    		$finder = $finder->files()->name('*Proxy.php')->in($proxiesDir);
    		foreach ($finder as $file) {
    			require_once $file;
    		}
        }
        parent::__construct($db, $options);
    }
    
	/**
     * Regenerates id that represents this storage.
     *
     * @param  boolean $destroy Destroy session when regenerating?
     *
     * @return boolean True if session regenerated, false if error
     *
     */
    public function regenerate($destroy = false)
    {
        if (self::$sessionIdRegenerated) {
            return;
        }

        // destroy current session if needed
        if($destroy && self::$sessionStarted) {
        	$this->sessionDestroy(session_id());
        }
        
        // regenerate a new session id once per object
        session_regenerate_id($destroy);

        // do a read operation to force re-creation of the database row with this new session id
        $this->sessionRead(session_id());
        
        self::$sessionIdRegenerated = true;
    }
}