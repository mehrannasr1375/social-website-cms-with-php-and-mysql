<?php
require_once "table.php";
class Category extends Table
{
    protected $data = array(
        "category_id" => 0,
        "category_name" => "",
        "parent_id" => 0
    );

    public static function getAllCategories()
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_categories ORDER BY category_id";
        $stmt = $conn -> prepare($query);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0){
            $cats = array();
            $rows = $stmt -> fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row){
                $cats[] = new Category($row);
            }
            $ret = $cats;
        }
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getCategoryById($category_id)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_categories WHERE category_id=:category_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':category_id',$category_id);
        $stmt -> execute();
        if($stmt -> rowCount() != 0)
            $ret = new Category($stmt -> fetch(PDO::FETCH_ASSOC));
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getCategoriesByParentId($parent_id)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_categories WHERE parent_id=:parent_id ";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':parent_id',$parent_id);
        $stmt -> execute();
        if($stmt -> rowCount() != 0){
            $cats = array();
            foreach ($stmt -> fetchAll(PDO::FETCH_ASSOC) as $row){
                $cats[] = new Category($row);
            }
            $ret = $cats;
        }
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function insertCategory($categoryArray)
    {
        $conn = self::connect();
        $category_name = $categoryArray['category_name'];
        $parent_id = $categoryArray['parent_id'];
        $query = "INSERT INTO tbl_categories(category_name,parent_id) VALUES ( :category_name, :parent_id)";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':category_name',$category_name);
        $stmt -> bindParam(':parent_id',$parent_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0)
            $ret = true;
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function deleteCategoryById($category_id)
    {
        $ret = true;
        $conn = self::connect();
        $query = "DELETE FROM tbl_posts_cats WHERE cat_id=:category_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':category_id',$category_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0)
            $ret = true;
        else $ret = false;

        $query = "DELETE FROM tbl_categories WHERE category_id=:category_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':category_id',$category_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0)
            $ret = true;
        else $ret = false;

        $query = "DELETE FROM tbl_categories WHERE parent_id=:category_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':category_id',$category_id);
        $stmt -> execute();
        if ($stmt -> rowCount() > 0)
            $ret = true;
        else $ret = false;

        self::disconnect($conn);
        return $ret;
    }//ok
}