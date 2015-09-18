<?php
require_once '../www-header.php';
if(isset($_POST['password']) && $userservice->isLoggedOn()){
        $password = $userservice->sanitisePassword($_POST['password']);
        $username = $currentUser->getUsername();
        $db = SemanticScuttle_Service_Factory::getDb();

        $query = 'SELECT '. $userservice->getFieldName('primary') .' FROM '. $userservice->getTableName() .' WHERE '. $userservice->getFieldName('username') .' = "'. $db->sql_escape($username) .'" AND '. $userservice->getFieldName('password') .' = "'. $db->sql_escape($password) .'"';

        if (!($dbresult = $db->sql_query($query))) {
            message_die(
                GENERAL_ERROR,
                'Could not get user',
                '', __LINE__, __FILE__, $query, $db
            );
            echo 'false';
        }
        else {
                $row = $db->sql_fetchrow($dbresult);
                $db->sql_freeresult($dbresult);

                if ($row) {
                    echo 'true';
                } 
                else {
                    echo 'false';
                }
        }
}
else {
        echo 'false';
}
?>
