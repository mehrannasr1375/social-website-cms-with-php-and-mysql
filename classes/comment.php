<?php
require_once "table.php";
class Comment extends Table
{
    protected $data = array(
        "comment_id" => 0,
        "full_name" => "",
        "email" => "",
        "website" => "",
        "comment" => "",
        "comment_time" => 0,
        "user_ip" => "",
        "post_id" => 0,
        "parent_id" => 0
    );

    public static function getAllComments()
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_comments ORDER BY comment_time DESC";
        $result = $conn -> query($query);
        if($result -> rowCount() != 0){
            $comments = array();
            foreach ($result -> fetchAll(PDO::FETCH_ASSOC) as $row){
                $comments[] = new Comment($row);
            }
            $ret = $comments;
        } else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getCommentsByPostId($post_id, $parent_id = 0)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_comments WHERE post_id=$post_id AND parent_id=$parent_id ORDER BY comment_time DESC";
        $result = $conn -> query($query);
        if($result -> rowCount() != 0){
            $comments = array();
            foreach ($result -> fetchAll(PDO::FETCH_ASSOC) as $row){
                $comments[] = new Comment($row);
                $id = $row['comment_id'];
                $conn2 = self::connect();
                $query2 = "SELECT * FROM tbl_comments WHERE post_id=$post_id AND parent_id=$id  ORDER BY comment_time DESC";
                $result2 = $conn2 -> query($query2);
                if($result2 -> rowCount() != 0){//exit from function
                    $comments = array_merge($comments,Comment::getCommentsByPostId($post_id,$id));
                }
            }
            $ret = $comments;
        }
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getCommentById($comment_id)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_comments WHERE comment_id=:comment_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":comment_id",$comment_id);
        $stmt -> execute();
        if($stmt->rowCount() != 0)
        {
            $row = $stmt -> fetch(PDO::FETCH_ASSOC);
            $ret = new Comment($row);
        } else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function insertComment($commentArray)
    {
        $ret = true;
        $conn = self::connect();
        $comment_time = time();
        $user_ip = $_SERVER['REMOTE_ADDR'];

        //query 1 : spam checking
        $query = "SELECT * FROM tbl_comments WHERE comment_time > :comment_time - 120 AND user_ip=:user_ip";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":comment_time",$comment_time);
        $stmt -> bindParam(":user_ip",$user_ip);
        if ($stmt -> rowCount() > 0){
            self::disconnect($conn);
            return false;
        }

        //query 2 : insert comment after find out that is not spam
        $query = "INSERT INTO tbl_comments(full_name,email,website,comment,comment_time,user_ip,post_id,parent_id)
                  VALUES(:full_name,:email,:website,:comment,:comment_time,:user_ip,:post_id,:parent_id)";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":full_name",$commentArray['full_name']);
        $stmt -> bindParam(":email",$commentArray['email']);
        $stmt -> bindParam(":website",$commentArray['website']);
        $stmt -> bindParam(":comment",$commentArray['comment']);
        $stmt -> bindParam(":comment_time",$comment_time);
        $stmt -> bindParam(":user_ip",$user_ip);
        $stmt -> bindParam(":post_id",$commentArray['post_id']);
        $stmt -> bindParam(":parent_id",$commentArray['parent_id']);
        $stmt -> execute();
        if($stmt -> rowCount() == 0)
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }

    public static function deleteCommentsByPostId($post_id)
    {
        $ret = true;
        $conn = self::connect();
        $query = "DELETE FROM tbl_comments WHERE post_id=:post_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":post_id",$post_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0)
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function deleteCommentById($comment_id)
    {
        $ret = true;
        $conn = self::connect();
        $query = "DELETE FROM tbl_comments WHERE comment_id=:comment_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":comment_id",$comment_id);
        $stmt -> execute();
        if ($stmt -> rowCount() == 0)
            $ret = false;
        $query = "DELETE FROM tbl_comments WHERE parent_id=:parent_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":parent_id",$comment_id);
        $stmt -> execute();
        if ($stmt -> rowCount() == 0)
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

}
