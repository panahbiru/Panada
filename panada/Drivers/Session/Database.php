<?php

/**
 * Panada Database session handler.
 *
 * @author	Iskandar Soesman
 *
 * @since	Version 0.3
 */
namespace Drivers\Session;

use Resources;

class Database extends Native
{
    /**
     * @var string Session table name.
     *             ID: Nama table session.
     */
    private $sessionDbName = 'sessions';
    private $sessionDbConn;

    public function __construct($config)
    {
        $this->sessionDbName    = $config['storageName'];
        $this->sessionDbConn    = new Resources\Database($config['driverConnection']);

        session_set_save_handler(
        array($this, 'sessionStart'),
        array($this, 'sessionEnd'),
        array($this, 'sessionRead'),
        array($this, 'sessionWrite'),
        array($this, 'sessionDestroy'),
        array($this, 'sessionGc')
    );

        parent::__construct($config);
    }

    /**
     * Required function for session_set_save_handler act like constructor in a class.
     *
     * @param string
     * @param string
     */
    public function sessionStart($save_path, $session_name)
    {
        //We don't need anythings at this time.
    }

    /**
     * Required function for session_set_save_handler act like destructor in a class.
     */
    public function sessionEnd()
    {
        //we also don't have do anythings too!
    }

    /**
     * Read session from db or file.
     *
     * @param string $id The session id
     *
     * @return string|array|object|bool
     */
    public function sessionRead($id)
    {
        $session = $this->sessionDbConn
            ->select('session_data')
            ->from($this->sessionDbName)
            ->where('session_id', '=', $id, 'and')
            ->where('session_expiration', '>', time())
            ->getOne();

        if ($session) {
            return $session->session_data;
        }

        return false;
    }

    /**
     * Get session data by session id.
     *
     * @param string
     *
     * @return int
     */
    private function sessionExist($id)
    {
        $session = $this->sessionDbConn
            ->select('session_data', 'session_expiration')
            ->from($this->sessionDbName)
            ->where('session_id', '=', $id)
            ->getOne();

        return $session;
    }

    /**
     * Write the session data.
     *
     * @param string
     * @param string
     *
     * @return bool
     */
    public function sessionWrite($id, $sess_data)
    {
        $curent_session = $this->sessionExist($id);
        $expiration    = $this->upcomingTime($this->sesionExpire);

        if ($curent_session) {
            if ((md5($curent_session->session_data) == md5($sess_data)) && ($curent_session->session_expiration > time() + 10)) {
                return true;
            }

            return $this->sessionDbConn
            ->update(
                $this->sessionDbName,
                array(
                'session_id' => $id,
                'session_data' => $sess_data,
                'session_expiration' => $expiration,
                ),
                array('session_id' => $id)
            );
        } else {
            return $this->sessionDbConn
            ->insert(
                $this->sessionDbName,
                array(
                'session_id' => $id,
                'session_data' => $sess_data,
                'session_expiration' => $expiration,
                )
            );
        }
    }

    /**
     * Remove session data.
     *
     * @param string
     *
     * @return bool
     */
    public function sessionDestroy($id)
    {
        return $this->sessionDbConn->delete($this->sessionDbName, array('session_id' => $id));
    }

    /**
     * Clean all expired record in db trigered by PHP Session Garbage Collection.
     *
     * @param date I don't think we still need this parameter since the expired date was store in db.
     *
     * @return bool
     */
    public function sessionGc($maxlifetime = '')
    {
        return $this->sessionDbConn->where('session_expiration', '<', time())->delete($this->sessionDbName);
    }
    
    /**
     * Get Session ID
     *
     */
    public function sessionId()
    {
        parse_str( $_SERVER['HTTP_COOKIE'], $sesId);
        return $sesId['PHPSESSID'];
    }

    /**
     * Save new session
     *
     * @param string|array
     * @param string|array|object
     * @return void
     */
    public function setValue($name, $value = '')
    {    
        if( \is_array($name) ) {
            foreach($name AS $key => $val)
            $_SESSION[$key] = $val;
        }
        else {
            $_SESSION[$name] = $value;
        }

        $this->sessionWrite( $this->sessionId(), json_encode($_SESSION) );
    }

    /**
     * Get the session vale.
     *
     * @param string
     * @return string|array|object
     */
    public function getValue($name)
    {    
        $session_data = $this->sessionRead( $this->sessionId() );
        $session_data = json_decode($session_data);

        return $session_data->$name;
    }
}
