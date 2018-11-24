<?php
require_once "table.php";
class Post extends Table
{
    protected $data = array(
        "post_id" => 0,
        "title" => "",
        "content" => "",
        "post_type" => 0,
        "user_id" => 0,
        "published" => 0,
        "allow_comments" => 0,
        "creation_time" => 0,
        "last_modify" => 0,
        "like_count" => 0,
        "dislike_count" => 0,
        "pages_count" => 0,
        "download_count" => 0,
        "comment_count" => 0,
        "pdf_path" => "",
        "image_path" => "",
        "size" => 0,
        "rate" => 0,
        "lang" => "",
        "user_name" => "",
        "first_name" => "",
        "last_name" => "",
        "categories" => array()
    );

    public function __set($property , $value)
    {
        if (array_key_exists($property,$this -> data)) {
            if ($property != "post_id") {
                $conn = self::connect();
                $query = "UPDATE tbl_posts SET $property='$value' WHERE post_id=$this->post_id";
                $conn -> query($query);
                $this -> data[$property] = $value;
                self::disconnect($conn);
            } else
                die("you can not change read only properties!");
        } else
            die("invalid property!(post __get)");
    }

    public static function getAllPosts($post_type = 1 , $published = 1 , $limit = 0 , $start = 0)
    {
        $conn=self::connect();
        if($published)
            $condition = "AND published=1";
        else
            $condition = " ";
        if($limit>0)
            $limiter = "LIMIT $start,$limit";
        else
            $limiter = "";
        $query = "SELECT tbl_posts.* , user_name , first_name , last_name FROM tbl_posts,tbl_users 
                      WHERE tbl_posts.user_id=tbl_users.user_id AND post_type=$post_type $condition 
                      ORDER BY creation_time DESC $limiter"; //InnerJoin(users & posts)
        $result = $conn -> query($query);
        if($result -> rowCount() != 0) {
            $posts = array();
            foreach ($result -> fetchAll(PDO::FETCH_ASSOC) as $row) {
                if($cats = PostCats::getAllByPostId($row['post_id'])) {
                    foreach ($cats as $cat) {
                        $row['categories'][] = $cat -> cat_id;
                    }
                }
                $posts[] = new Post($row);
            }
            $ret = $posts;
        }else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }

    public static function getPostById($post_id)
    {
        $conn = self::connect();
        $query = "SELECT tbl_posts.* , user_name , first_name , last_name
                   FROM tbl_posts,tbl_users
                   WHERE tbl_posts.user_id=tbl_users.user_id AND tbl_posts.post_id= ?";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(1,$post_id);
        $stmt -> execute();
        if($stmt -> rowCount() > 0) {
            $row = $stmt -> fetch(PDO::FETCH_ASSOC);
            if($cats = PostCats::getAllByPostId($row['post_id'])){
                foreach ($cats as $cat) {
                    $row['categories'][] = $cat -> cat_id;
                }
            }
            $ret = new Post($row);
        }else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getPostsByCategory($cat_id , $published = true , $childs = true , $limit = 0 , $start = 0)
    {
        $conn = self::connect();
        if($limit > 0)
            $limiter = "LIMIT $start ,$limit";
        else
            $limiter = "";
        if($published == false)
            $condition = "";
        else
            $condition = " published=1";
        if($posts = PostCats::getAllByCatId($cat_id , $childs = true)) {
            $ids = "AND post_id IN (";
            foreach ($posts as $post) {
                $ids .= $post -> post_id . ",";
            }
            $ids = substr($ids , 0 , strlen($ids)-1) . ")";
        }
        else {
            self::disconnect($conn);
            return false;
        }

        $query = "SELECT tbl_posts.* , user_name , first_name , last_name
                    FROM tbl_posts,tbl_users
                    WHERE tbl_posts.user_id=tbl_users.user_id
                    AND $condition $ids
                    ORDER BY creation_time DESC $limiter"; //InnerJoin(users & posts)
        $result = $conn -> query($query);
        if($result -> rowCount() != 0){
            $posts = array();
            foreach ($result -> fetchAll(PDO::FETCH_ASSOC) as $row) {
                if($cats = PostCats::getAllByPostId($row['post_id'])) {
                    foreach ($cats as $cat) {
                        $row['categories'][] = $cat -> cat_id;
                    }
                }
                $posts[] = new Post($row);
            }
            $ret = $posts;
        }
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }

    public static function getPostsByUserId($user_id , $post_type = 1 , $published = true , $limit = 0 , $start = 0)
    {
        $conn = self::connect();
        if($limit > 0)
            $limiter = " LIMIT $start,$limit";
        else
            $limiter = "";
        if($published == false)
            $condition = "";
        else
            $condition = " AND published = 1";

        $query = "SELECT tbl_posts.* , user_name , first_name , last_name
                    FROM tbl_posts,tbl_users
                    WHERE tbl_posts.user_id=tbl_users.user_id AND post_type=$post_type $condition
                    AND tbl_posts.user_id=$user_id  ORDER BY creation_time DESC $limiter"; //InnerJoin(users & posts)
        $result = $conn -> query($query);
        if($result -> rowCount() != 0){
            $posts = array();
            foreach ($result -> fetchAll(PDO::FETCH_ASSOC) as $row){
                if($cats = PostCats::getAllByPostId($row['post_id'])) {
                    foreach ($cats as $cat) {
                        $row['categories'][] = $cat -> cat_id;
                    }
                }
                $posts[] = new Post($row);
            }
            $ret = $posts;
        }else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }

    public static function insertPost($postArray)
    {
        $conn = self::connect();
        $title = $postArray['title'];
        $content = $postArray['content'];
        $post_type = $postArray['post_type'];
        $user_id = $postArray['user_id'];
        $published = $postArray['published'];
        $allow_comments =$postArray['allow_comments'];
        $creation_time = time();
        $like_count = 0;
        $dislike_count = 0;
        $comment_count = 0;
        $pages_count = $postArray['pages_count'];
        $rate = $postArray['rate'];
        $lang = $postArray['lang'];
        $size = $postArray['size'];
        $pdf_path = $postArray['pdf_path'];
        $image_path = $postArray['image_path'];
        $categories = $postArray['categories'];//in second query

        $query = "INSERT INTO tbl_posts(title,
                                        content,
                                        post_type,
                                        user_id,
                                        published,
                                        allow_comments,
                                        creation_time,
                                        like_count,
                                        dislike_count,
                                        comment_count,
                                        pages_count,
                                        rate,
                                        lang,
                                        size,
                                        pdf_path,
                                        image_path
                                        )
                                        VALUES (
                                            '$title',
                                            '$content',
                                            $post_type,
                                            $user_id,
                                            $published,
                                            $allow_comments,
                                            $creation_time,
                                            $like_count,
                                            $dislike_count,
                                            $comment_count,
                                            $pages_count,
                                            $rate,
                                            '$lang',
                                            $size,
                                            '$pdf_path',
                                            '$image_path'
                                        )";
        if(!$conn -> query($query))
            return false;

        //SECOND QUERY
        $post_id = $conn -> lastInsertId();
        $ret = $post_id;
        foreach ($categories as $cat){
            $query = "INSERT INTO tbl_posts_cats(post_id,cat_id) VALUES ($post_id,$cat)";
            if (!$conn -> query($query))
                $ret = false;
        }
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function deletePostById($post_id)
    {
        $conn = self::connect();
        PostCats::deleteByPostId($post_id);
        Comment::deleteCommentsByPostId($post_id);
        $query = "DELETE FROM tbl_posts WHERE post_id=:post_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":post_id",$post_id);
        if (!$stmt -> execute())
            $ret = false;
        else
            $ret = true;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function updatePost($postArray)
    {
        $ret = true;
        $conn = self::connect();
        $last_modify = time();
        $categories = $postArray['categories'];

        $query = "UPDATE tbl_posts SET
                    title=:title,
                    content=:content,
                    published=:published,
                    allow_comments=:allow_comments,
                    pages_count=:pages_count,
                    rate=:rate,
                    lang=:lang,
                    last_modify=:last_modify
                    WHERE post_id =:post_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":title",$postArray['title']);
        $stmt -> bindParam(":content",$postArray['content']);
        $stmt -> bindParam(":published",$postArray['published']);
        $stmt -> bindParam(":allow_comments",$postArray['allow_comments']);
        $stmt -> bindParam(":pages_count",$postArray['pages_count']);
        $stmt -> bindParam(":rate",$postArray['rate']);
        $stmt -> bindParam(":lang",$postArray['lang']);
        $stmt -> bindParam(":last_modify",$last_modify);
        $stmt -> bindParam(":post_id",$postArray['post_id']);

        if (!$stmt -> execute())
            $ret = false;

        if (!PostCats::deleteByPostId($postArray['post_id']))
            $ret = false;
        echo "";

        foreach ($categories as $category){
            $query = "INSERT INTO tbl_posts_cats(post_id,cat_id) VALUES (:post_id,:category)";
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":post_id",$post_id);
            $stmt -> bindParam(":category",$category);
            if (!$stmt -> execute())
                $ret = false;
            self::disconnect($conn);
            return $ret;
        }
    }//ok

    public static function searchPosts($q , $titleSearch = true , $contentSearch = true , $published = true , $limit = 0 , $start = 0)
    {
        $conn = self::connect();
        if($limit>0)
            $limiter = "LIMIT $start,$limit";
        else
            $limiter = " ";
        if($titleSearch and $contentSearch)
            $condition = "(title LIKE '%$q%' OR content LIKE '%$q%')";
        elseif ($titleSearch)
            $condition = "title LIKE '%$q%'";
        elseif ($contentSearch)
            $condition = "content LIKE '%$q%'";
        else
            return false;
        if($published == true)
            $condition .= "AND published=1";
        $query = "SELECT tbl_posts.* , user_name , first_name , last_name FROM tbl_posts,tbl_users WHERE tbl_posts.user_id=tbl_users.user_id AND $condition ORDER BY creation_time DESC $limiter"; //InnerJoin(users & posts)
        $result = $conn -> query($query);
        if($result -> rowCount() != 0)
        {
            $posts = array();
            foreach ($result -> fetchAll(PDO::FETCH_ASSOC) as $row){
                if($cats = PostCats::getAllByPostId($row['post_id'])) {
                    foreach ($cats as $cat) {
                        $row['categories'][] = $cat -> cat_id;
                    }
                }
                $posts[] = new Post($row);
            }
            $ret = $posts;
        }else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function likePost($post_id , $user_id)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_like WHERE post_id=:post_id AND user_id=:user_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":post_id",$post_id);
        $stmt -> bindParam(":user_id",$user_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0) {
            return false;
        }
        else {
            $query = "INSERT INTO tbl_like(post_id,user_id) VALUES (:post_id,:user_id)";
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":post_id",$post_id);
            $stmt -> bindParam(":user_id",$user_id);
            if ($stmt -> execute())
            {
                $query = "SELECT * FROM tbl_posts WHERE post_id=:post_id";
                $stmt = $conn -> prepare($query);
                $stmt -> bindParam(":post_id",$post_id);
                $stmt -> execute();
                if ($stmt -> rowCount() > 0)
                {
                    $count = (int)($stmt -> fetch(PDO::FETCH_ASSOC)['like_count']);
                    $count += 1;
                    $query = "UPDATE tbl_posts SET like_count=:count WHERE post_id=:post_id";
                    $stmt = $conn -> prepare($query);
                    $stmt -> bindParam(":count",$count);
                    $stmt -> bindParam(":post_id",$post_id);
                    if ($stmt -> execute())
                        return $count;
                    else{
                        self::disconnect($conn);
                        return false;
                    }
                }
                else{
                    self::disconnect($conn);
                    return false;
                }
            }
            else{
                self::disconnect($conn);
                return false;
            }
        }
    }//ok

    public static function dislikePost($post_id , $user_id)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_dislike WHERE post_id=:post_id AND user_id=:user_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":post_id",$post_id);
        $stmt -> bindParam(":user_id",$user_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0) {
            return false;
        }
        else {
            $query = "INSERT INTO tbl_dislike(post_id,user_id) VALUES (:post_id,:user_id)";
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":post_id",$post_id);
            $stmt -> bindParam(":user_id",$user_id);
            if ($stmt -> execute()) {
                $query = "SELECT * FROM tbl_posts WHERE post_id=:post_id";
                $stmt = $conn -> prepare($query);
                $stmt -> bindParam(":post_id",$post_id);
                $stmt -> execute();
                if ($stmt -> rowCount() > 0){
                    $count = (int)($stmt -> fetch(PDO::FETCH_ASSOC)['like_count']);
                    $count += 1;
                    $query = "UPDATE tbl_posts SET like_count=:count WHERE post_id=:post_id";
                    $stmt = $conn -> prepare($query);
                    $stmt -> bindParam(":count",$count);
                    $stmt -> bindParam(":post_id",$post_id);
                    if ($stmt -> execute())
                        return $count;
                    else{
                        self::disconnect($conn);
                        return false;
                    }
                }
                else{
                    self::disconnect($conn);
                    return false;
                }
            }
            else{
                self::disconnect($conn);
                return false;
            }

        }

    }//ok

    public static function getLikesCount($post_id){
        $conn = self::connect();
        $query = "SELECT like_count FROM tbl_posts WHERE post_id=:post_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":post_id",$post_id);
        if ($stmt -> execute()) {
            return (int)($stmt -> fetch(PDO::FETCH_ASSOC)['like_count']);
        }else
            return "0";
    }//ok

    public static function getDislikesCount($post_id){
        $conn = self::connect();
        $query = "SELECT dislike_count FROM tbl_posts WHERE post_id=:post_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":post_id",$post_id);
        if ($stmt -> execute()) {
            return (int)($stmt -> fetch(PDO::FETCH_ASSOC)['dislike_count']);
        }else
            return "0";
    }//ok

    public static function getPostImage($post_id){
        $conn = self::connect();
        $query = "SELECT image_path FROM tbl_posts WHERE post_id=:post_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":post_id",$post_id);
        if ($stmt -> execute()) {
            $row = $stmt -> fetch(PDO::FETCH_ASSOC)['image_path'];
        }else
            $row = false;
        self::disconnect($conn);
        return $row;
    }//ok

    public static function getTopPosts($limit = 5){
        $conn = self::connect();
        $query = "SELECT * FROM tbl_posts WHERE post_type=1 AND published=1 AND rate >= 5 ";
        $stmt = $conn -> prepare($query);
        $stmt -> execute();
        if($stmt -> rowCount()){
            $res = $stmt -> fetchAll(PDO::FETCH_ASSOC);
            $posts = array();
            foreach ($res as $row){
                $posts[] = new Post($row);
            }
            $ret = $posts;
        }else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok
}