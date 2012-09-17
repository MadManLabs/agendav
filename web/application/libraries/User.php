<?php 

namespace AgenDAV;

if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

/*
 * Copyright 2012 Jorge López Pérez <jorge@adobo.org>
 *
 *  This file is part of AgenDAV.
 *
 *  AgenDAV is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  any later version.
 *
 *  AgenDAV is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with AgenDAV.  If not, see <http://www.gnu.org/licenses/>.
 */

class User {
    private $username, $passwd, $displayname, $mail;
    private $is_authenticated = null;
    private $preferences;
    private $calendars = null;
    private $CI;
    private static $instance = null;

    /**
     * Creates a user instance. Loads data from session, if available
     */
    public function __construct() {
        $this->CI =& get_instance();
        
        foreach (array('username', 'passwd', 'is_authenticated') as $n) {
            if (false !== $current = $this->CI->session->userdata($n)) {

                // Decrypt password
                if ($n == 'passwd') {
                    $current = $this->CI->encrypt->decode($current);
                }

                $this->$n = $current;
            }
        }
    }

    /**
     * Gets current user object, if defined (singleton)
     *
     * @return \AgenDAV\User Current user object
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new User();
        }

        return self::$instance;
    }


    /**
     * Set user credentials
     *
     * @param string $username User name
     * @param string $passwd Clear text password
     * @return void
     */
    public function setCredentials($username, $passwd) {
        $this->username = mb_strtolower($username);
        $this->passwd = $passwd;
    }


    /**
     * Gets current user preferences
     *
     * @param boolean $force Force reloading preferences
     * @return AgenDAV\Data\Preferences Current user preferences
     */
    public function getPreferences($force = false) {
        if ($force === true || $this->preferences === null) {
            $this->preferences =
                $this->CI->preferences->get($this->username, $force);
        }

        return $this->preferences;
    }

    /**
     * Gets current user name
     *
     * @return string User name
     */
    public function getUsername() {
        return $this->username;
    }

    /**
     * Gets user password
     *
     * @return string Password
     */
    public function getPasswd() {
        return $this->passwd;
    }

    /**
     * Creates new session
     *
     * @return void
     */
    public function newSession() {
        $data = array(
                'username' => $this->username,
                'passwd' => $this->CI->encrypt->encode($this->passwd),
                'is_authenticated' => $this->is_authenticated,
                );
        $this->CI->session->set_userdata($data);
    }

    /**
     * Empty current session
     *
     * @return void
     */
    public function removeSession() {
        $data = array(
                'username' => '',
                'passwd' => '',
                'is_authenticated' => '',
                );
        $this->CI->session->unset_userdata($data);
        $this->CI->session->sess_destroy();
    }

    /**
     * Checks valid authentication against CalDAV server
     *
     * @return boolean Current user is logged in
     */
    public function isAuthenticated() {
        if (empty($this->username) || empty($this->passwd)) {
            return false;
        } elseif ($this->is_authenticated !== true) {
            $this->is_authenticated =
                $this->CI->caldav->check_server_authentication(
                        $this->username,
                        $this->passwd);
        }

        return $this->is_authenticated;
    }

    /**
     * Checks if current user is authenticated. If not, user is redirected
     * to login page
     */
    public function forceAuthentication() {
        if (!$this->isAuthenticated()) {
            redirect('/login');
        }
    }

    /**
     * Retrieves all user calendars
     *
     * @param boolean $force Force calendar reloading
     */
    public function allCalendars($force = false) {
        if ($force === true || $this->calendars === null) {
            $calendars = $this->CI->caldav->all_user_calendars(
                    $this->username, $this->passwd);

            // Hide calendars user doesn't want to be shown
            $calendars = $this->removeHiddenCalendars($calendars);

            // Default calendar
            $calendars = $this->setDefaultCalendar($calendars);
        }

        return $calendars;
    }


    /**
     * Remove calendars which are marked to be hidden from calendar list
     *
     * @param Array $calendars Calendars fetched from server
     * @return Array Resulting calendar list
     */
    public function removeHiddenCalendars(&$calendars) {
        $hidden_calendars = $this->getPreferences()->hidden_calendars;
        return array_diff_key($calendars, $hidden_calendars);
    }

    /**
     * Sets default calendar
     *
     * @param Array $calendars Available calendars
     * @return Array Modified calendars with default calendar present
     */
    public function setDefaultCalendar(&$calendars) {
        $default_calendar = $this->getPreferences()->default_calendar;
        if ($default_calendar !== null &&
                isset($calendars[$default_calendar])) {
            $calendars[$default_calendar]->default_calendar = true;
        } elseif (count($calendars) > 0) {
            $first = array_shift(array_keys($calendars));
            $calendars[$first]->default_calendar = true;
        }

        return $calendars;
    }

}
