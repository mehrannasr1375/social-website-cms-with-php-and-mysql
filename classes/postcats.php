<?php
require_once "table.php";
class PostCats extends Table
{
    protected $data = array(
        "id" => 0,
        "post_id" => 0,
        "cat_id" => 0
    );

    public static function getAllByPostId($post_id)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_posts_cats WHERE post_id=:post_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":post_id",$post_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0){
            $cats = array();
            $rows = $stmt -> fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cats[] = new PostCats($row);
            }
            $ret = $cats;
        } else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getAllByCatId($cat_id , $childs = true)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_posts_cats WHERE cat_id=$cat_id";
        if ($childs){
            if ($child_cats = Category::getCategoriesByParentId($cat_id)){
                $child_ids = "(";
                foreach ($child_cats as $child) {
                    $child_ids .= $child -> category_id . ",";
                }
                $child_ids = substr($child_ids, 0, strlen($child_ids) - 1) . ")";
                $query = "SELECT * FROM tbl_posts_cats WHERE cat_id=$cat_id OR cat_id IN $child_ids";
//                echo $query;
            }
        }
        $res = $conn -> query($query);
        if ($res -> rowCount()) {
            $cats = array();
            $rows = $res -> fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cats[] = new PostCats($row);
            }
            $ret = $cats;
        } else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }

    public static function getByPostAndCat($post_id,$cat_id)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_posts_cats WHERE post_id=:post_id AND cat_id=:cat_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':post_id',$post_id);
        $stmt -> bindParam(':cat_id',$cat_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0)
            $ret = true;
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function deleteByPostId($post_id)
    {
        $conn = self::connect();
        $query = "DELETE FROM tbl_posts_cats  WHERE post_id=:post_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':post_id',$post_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0)
            $ret = true;
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function deleteByCatId($cat_id)
    {
        $conn = self::connect();
        $query = "DELETE FROM tbl_posts_cats  WHERE cat_id=:cat_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':cat_id',$cat_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0)
            $ret = true;
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok
}