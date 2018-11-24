<?php
require_once "table.php";
class Friendship extends Table
{
    protected $data = array(
        "user_id_1" => 0,
        "user_id_2" => 0,
        "follower_user_name" => "",
        "follower_email" => "",
        "follower_avatar_path" => ""
    );

    public static function getUserFollowers($user_id , $accepted = 0)
    {
        $conn = self::connect();
        $query = "SELECT tbl_friendship.user_id_1,
                        tbl_friendship.user_id_2,
                        tbl_users.user_name AS  follower_user_name,
                        tbl_users.user_email AS  follower_email,
                        tbl_users.user_avatar_path AS  follower_avatar_path
                        FROM tbl_friendship, tbl_users
                        WHERE user_id_2=:user_id
                        AND tbl_friendship.user_id_1 = tbl_users.user_id
                        AND accepted=:accepted" ;
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_id",$user_id);
        $stmt -> bindParam(":accepted",$accepted);
        $stmt -> execute();
        if($stmt -> rowCount() > 0){
            $friends = $stmt -> fetchAll(PDO::FETCH_ASSOC);
            $friend_array = array();
            foreach ($friends as $row){
                $friend_array[] = new Friendship($row);
            }
            $ret = $friend_array;
        }
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getUserFriends($user_id , $accepted = 1)
    {
        $conn = self::connect();
        $query = "SELECT tbl_friendship.user_id_1, 
                        tbl_friendship.user_id_2,
                        tbl_users.user_name AS  follower_user_name,
                        tbl_users.user_email AS  follower_email,
                        tbl_users.user_avatar_path AS  follower_avatar_path
                        FROM tbl_friendship, tbl_users
                        WHERE user_id_1=:user_id
                        AND tbl_friendship.user_id_2 = tbl_users.user_id
                        AND accepted=:accepted" ;
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_id",$user_id);
        $stmt -> bindParam(":accepted",$accepted);
        $stmt -> execute();
        if($stmt -> rowCount() > 0){
            $friends = $stmt -> fetchAll(PDO::FETCH_ASSOC);
            $friend_array = array();
            foreach ($friends as $row){
                $friend_array[] = new Friendship($row);
            }
            $ret = $friend_array;
        }
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function sendFollowRequest($sender_user_id,$target_user_id)
    {
        $conn = self::connect();
        $query = "INSERT INTO tbl_friendship(user_id_1,user_id_2,accepted) VALUES (:user_1, :user_2, 0)";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':user_1',$sender_user_id);
        $stmt -> bindParam(':user_2',$target_user_id);
        $result = $stmt -> execute();
        self::disconnect($conn);
        if ($result)
            return true;
        else
            return false;
    }//ok

    public static function acceptFollowRequest($sender_user_id,$target_user_id)
    {
        $conn = self::connect();
        $query = "UPDATE tbl_friendship SET accepted = 1 WHERE user_id_1 = :user_1 AND user_id_2 = :user_2";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':user_1',$sender_user_id);
        $stmt -> bindParam(':user_2',$target_user_id);
        $result = $stmt -> execute();
        if ($result){
            $ret =  true;
            $query = "SELECT tbl_users.followers_count FROM tbl_users WHERE user_id=:user_id"; // GET LAST COUNT NUMBER
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":user_id",$target_user_id);
            $stmt -> execute();
            $count = (int)($stmt -> fetch(PDO::FETCH_ASSOC)['followers_count'])+1;
            $query = "UPDATE tbl_users SET followers_count=:new_count WHERE user_id=:user_id "; // SET NEW COUNT NUMBER
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(':user_id',$target_user_id);
            $stmt -> bindParam(':new_count',$count);
            $stmt -> execute();
        }
        else
            $ret = false;

         return $ret;
    }//ok

    public static function rejectFollowRequest($sender_user_id,$target_user_id)
    {
        $conn = self::connect();
        $query = "DELETE FROM tbl_friendship WHERE user_id_1 = :user_1 AND user_id_2 = :user_2";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':user_1',$sender_user_id);
        $stmt -> bindParam(':user_2',$target_user_id);
        if ($stmt -> execute())
            return true;
        else
            return false;
    }//ok

    public static function getFriendsPosts($user_id)
    {
        $conn = self::connect();
        //first query(get friends ids):
        $query = "SELECT user_id_2 FROM tbl_friendship WHERE user_id_1=$user_id AND accepted=1;";
        $result = $conn -> query($query);
        if($result -> rowCount() > 0) {
            $following = array();
            $rows = $result -> fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as $row){
                $following[] = $row['user_id_2'];
            }
            $in = implode("," , $following);//get id of peoples, that, has been followed by target user(user_id)
            //second query(get posts of friends):
            $query = "SELECT tbl_posts.* , user_name , first_name , last_name
                      FROM tbl_posts,tbl_users
                      WHERE tbl_posts.user_id=tbl_users.user_id
                      AND tbl_users.user_id IN ($in)
                      ORDER BY creation_time DESC"; //InnerJoin(users & posts & friendship)
            $result = $conn -> query($query);
            if($result -> rowCount() > 0) {
                $posts = array();
                $rows = $result -> fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row){
                    if($cats = PostCats::getAllByPostId($row['post_id'])) {
                        foreach ($cats as $cat){
                            $row['categories'][] = $cat -> cat_id;
                        }
                    }
                    $posts[] = new Post($row);
                }
                $ret = $posts;
            }
        }
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getFollowersCount($user_id)
    {
        $conn = self::connect();
        $query = "SELECT count(*) FROM tbl_friendship WHERE user_id_2=:user_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_id",$user_id);
        $stmt -> execute();
        if($stmt -> rowCount() > 0)
            $ret = $stmt -> fetch(PDO::FETCH_ASSOC)['count(*)'];
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok
}
