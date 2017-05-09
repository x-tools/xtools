<?php

namespace Xtools;

class UserRepository extends Repository
{

    /**
     * Get the user's ID.
     * @param string $databaseName The database to query.
     * @param string $username The username to find.
     * @return int
     */
    public function getId($databaseName, $username)
    {
        $userTable = $this->getTableName($databaseName, 'user');
        $sql = "SELECT user_id FROM $userTable WHERE user_name = :username LIMIT 1";
        $resultQuery = $this->projectsConnection->prepare($sql);
        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();
        $userId = (int)$resultQuery->fetchColumn();
        return $userId;
    }
}
