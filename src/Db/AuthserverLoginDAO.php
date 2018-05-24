<?php
namespace OCA\AuthserverLogin\Db;

use OCP\IDBConnection;

class AuthserverLoginDAO
{

    private $db;

    public function __construct(IDBConnection $db)
    {
        $this->db = $db;
    }

    public function findUser($authserverGuid)
    {
        $sql = 'SELECT * FROM `*PREFIX*authserver_login` ' . 'WHERE `authserver_guid` = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(1, $authserverGuid);
        $stmt->execute();

        $row = $stmt->fetch();

        $stmt->closeCursor();

        return $row ? $row['uid'] : null;
    }

    public function connect($user, $authserverGuid)
    {
        $sql = 'INSERT INTO `*PREFIX*authserver_login` (`uid`, `authserver_guid`) VALUES(?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(1, $user);
        $stmt->bindParam(2, $authserverGuid);
        $stmt->execute();
    }

    public function disconnect($user)
    {
        $sql = 'DELETE FROM `*PREFIX*authserver_login` WHERE `uid` = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(1, $user);
        $stmt->execute();
    }
}
