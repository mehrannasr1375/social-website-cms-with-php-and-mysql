<?php
require_once "table.php";
class User extends Table
{
    protected $data = array(
        "user_id" => 0,
        "user_name" => "",
        "user_pass" => "",
        "user_email" => "",
        "signup_time" => 0,
        "first_name" => "",
        "last_name" => "",
        "activated" => 0,
        "activation_code" => "",
        "user_type_id" => 0,
        "user_type_name" => "",
        "friends" => array(),
        "user_avatar_path" => ""
    );

    public static function getAllUsers()
    {
        $conn = self::connect();
        $query = "SELECT tbl_users.* , tbl_user_types.user_type AS user_type_name FROM tbl_users,tbl_user_types  WHERE tbl_user_types.user_type_id = tbl_users.user_type_id ORDER BY tbl_users.user_id ";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":comment_id",$comment_id);
        $stmt -> execute();
        if($stmt -> rowCount() != 0){
            $users = array();
            foreach ($stmt -> fetchAll(PDO::FETCH_ASSOC) as $row){
                $users[] = new User($row);
            }
            $ret = $users;
        }else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getUserById($user_id)
    {
        $conn = self::connect();
        $query = "SELECT tbl_users.* , tbl_user_types.user_type AS user_type_name FROM tbl_users,tbl_user_types WHERE tbl_user_types.user_type_id=tbl_users.user_type_id AND user_id=:user_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_id",$user_id);
        $stmt -> execute();
        if($stmt -> rowCount() != 0){
            $row = $stmt -> fetch(PDO::FETCH_ASSOC);
            $ret = new User($row);
        }else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getUserByPostId($post_id)
    {
        $conn = self::connect();
        $query = "SELECT tbl_posts.* FROM tbl_users , tbl_posts WHERE tbl_users.user_id = tbl_posts.user_id AND post_id =:post_id;";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":post_id",$post_id);
        $stmt -> execute();
        if($stmt -> rowCount() > 0)
            $ret = $stmt -> fetch(PDO::FETCH_ASSOC)['user_id'];
        else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function insertUser($user_array)
    {
        $conn = self::connect();
        $signup_time = time();
        $user_email = $user_array['user_email'];
        $user_name = $user_array['user_name'];
        $salt = "10-20/3&poonzdah";
        $hashed_pass = md5($user_array['user_pass'].$salt);
        $user_type_id = $user_array['user_type_id'];
        if($user_type_id == 1)
            $activated = 1;
        else if ($user_type_id == 2 or $user_type_id == 3)
            $activated = 0;

        $query = "SELECT * FROM tbl_users WHERE user_name=:user_name";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_name",$user_name);
        $stmt -> execute();
        if($stmt -> rowCount() != 0)
            return array(false,"خطا: این نام کاربری قبلا توسط کاربر دیگری استفاده شده است! لطفا نام کاربری دیگری انتخاب نمایید.");

        $query = "SELECT * FROM tbl_users WHERE user_email=:user_email";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_email",$user_email);
        $stmt -> execute();
        if($stmt -> rowCount() != 0)
            return array(false,"خطا: این ایمیل توسط کاربر دیگری استفاده شده است! لطفا از ایمیل دیگری استفاده نمایید.");

        $query = "INSERT INTO tbl_users(user_name,user_pass,user_email,signup_time,first_name,last_name,activated,user_type_id,user_avatar_path)
                  VALUES(:user_name,:user_pass,:user_email,:signup_time,:first_name,:last_name,:activated,:user_type_id,:user_avatar_path)";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_name",$user_name);
        $stmt -> bindParam(":user_pass",$hashed_pass);
        $stmt -> bindParam(":user_email",$user_email);
        $stmt -> bindParam(":signup_time",$signup_time);
        $stmt -> bindParam(":first_name",$user_array['first_name']);
        $stmt -> bindParam(":last_name",$user_array['last_name']);
        $stmt -> bindParam(":activated",$activated);
        $stmt -> bindParam(":user_type_id",$user_type_id);
        $stmt -> bindParam(":user_avatar_path",$user_array['user_avatar_path']);
        if(! $stmt -> execute())
            $ret = array(false,"خطا: در ارتباط با پایگاه داده مشکلی رخ داده است!");
        else {
            if($user_type_id == 1)
                $ret = array(true,"درخواست با موفقیت انجام پذیرفت!");
            else
                $ret = array(true,"کاربر گرامی: یک ایمیل به آدرس ایمیلتان ارسال گردید. جهت فعالسازی حساب کاربری خود ایمیلتان راچک کرده و روی لینک فعالسازی کلیک نمایید. با تشکر.");
        }

        if(!User::sendActivationEmail($user_name,$user_email))
            $ret = array(false,"خطا: مشکلی در ارسال ایمیل تاییدیه رخ داده است!");
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function sendActivationEmail($user_name , $user_email)
    {
        $conn = self::connect();

        //query 1 = STOP DOS ATTACK FOR MAIL LIMITATION
        $now_time = time();
        $query = "SELECT * FROM tbl_sent_mails WHERE user_email=:user_email AND send_time > :now_time - 3600";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_email",$user_email);
        $stmt -> bindParam(":now_time",$now_time);
        $stmt -> execute();
        if ($stmt -> rowCount() > 30) {
            self::disconnect($conn);
            return false;
        }

        //query 2 = UPDATE ACTIVATION CODE INTO TBL_USERS
        $activation_code = rand(1000000,9999999);
        $query = "UPDATE tbl_users SET activision_code=:activation_code WHERE user_name=:user_name";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_name",$user_name);
        $stmt -> bindParam(":activation_code",$activation_code);
        if(!$stmt -> execute())
            return false;

        $mail = new PHPMailer();
        $mail -> Subject = "لینک فعالسازی حساب کاربری";
        $mail -> Body = <<<EOS
<p style=\"direction=rtl;font-family=tahoma\">جهت فعالسازی حساب خود روی این لینک کلیک نمایید:<br/>
<a href=\"http://localhost/pdf-download/?action=activate&username=$user_name&code=$activation_code\" target='_blank'>http://localhost/pdf-download/?action=activate&username=$user_name&code=$activation_code</a>
</p>
EOS;
        $mail -> addAddress($user_email);
        $mail -> FromName = 'pdf-download-center';
        $mail -> isHTML();
        $mail -> isSMTP();
        $mail -> SMTPAuth = true;
        $mail -> CharSet = 'utf-8';
        $mail -> SMTPSecure = 'ssl';
        $mail -> Port = 465;
        $mail -> Host = 'smtp.gmail.com';
        $mail -> Username = EMAIL_USER_NAME;
        $mail -> Password = EMAIL_PASSWORD;

        //query 3 = INSERT MAIL TIME TO TBL_SENT_MAILS
        $send_time = time();
        $query = "INSERT INTO tbl_sent_mails(user_email,send_time) VALUES(:user_email,:send_time)";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_email",$user_email);
        $stmt -> bindParam(":send_time",$send_time);
        $stmt -> execute();

        //query 4 = DELETE OLD RECORDS FROM TBL_SENT_MAILS
        $query = "DELETE FROM tbl_sent_mails WHERE send_time < :send_time - 3600";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":send_time",$send_time);
        $stmt -> execute();

        return $mail -> send(); //TRUE OR FALSE
    }//ok

    public static function activateUser($user_name , $activation_code)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_users WHERE user_name=:user_name AND activision_code=:activation_code";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_name",$user_name);
        $stmt -> bindParam(":activation_code",$activation_code);
        $stmt -> execute();
        self::disconnect($conn);
        if($stmt -> rowCount() != 0)
        {
            $query = "UPDATE tbl_users SET activated=1 WHERE user_name=:user_name";
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":user_name",$user_name);
            $stmt -> execute();
            return true;
        }
        else
            return false;
    }//ok

    public static function authenticateUser($user_name , $user_pass)
    {
        $conn = self::connect();
        $salt = "10-20/3&poonzdah";
        $hashed_pass = md5($user_pass.$salt);
        $query = "SELECT tbl_users.* , tbl_user_types.user_type AS user_type_name FROM tbl_users,tbl_user_types WHERE  tbl_users.user_type_id = tbl_user_types.user_type_id
                  AND tbl_users.user_name =:user_name AND user_pass =:user_pass AND activated = 1";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_name",$user_name);
        $stmt -> bindParam(":user_pass",$hashed_pass);
        $stmt -> execute();
        if($stmt -> rowCount() != 0){
            $res = $stmt -> fetch(PDO::FETCH_ASSOC);
            return new User($res);
        }
        else
            return false;
    }//ok

    public static function getUserByName($user_name)
    {
        $conn = self::connect();
        $query = "SELECT tbl_users.* , tbl_user_types.user_type AS user_type_name FROM tbl_users,tbl_user_types  WHERE tbl_user_types.user_type_id=tbl_users.user_type_id
        AND tbl_users.user_name=:user_name";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_name",$user_name);
        $stmt -> execute();
        if($stmt -> rowCount() != 0)
            return new User($stmt -> fetch(PDO::FETCH_ASSOC));
            $ret = false;
        return $ret;
    }//ok

    public static function rememberUser($user_email)
    {
        //query 1 = STOP DOS ATTACK FOR MAIL LIMITATION
        $conn = self::connect();
        $now_time = time();
        $query = "SELECT * FROM tbl_sent_mails WHERE user_email=:user_email AND send_time > :now_time - 3600";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_email",$user_email);
        $stmt -> bindParam(":now_time",$now_time);
        $stmt -> execute();
        if ($stmt -> rowCount() > 30) {
            self::disconnect($conn);
            return false;
        }

        //query 2 = CHECK MAIL EXISTS, IF EXISTS SEND REMEMBER MAIL
        $query = "SELECT * FROM tbl_users WHERE user_email=:user_email";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(":user_email",$user_email);
        $stmt -> execute();
        if ($stmt -> rowCount() != 0) {
            $user = $stmt -> fetch(PDO::FETCH_ASSOC);
            $user_name = $user['user_name'];

            $salt = "10-20/3&poonzdah";
            $new_pass =  rand(100000,999999);
            $new_hashed_pass = md5($new_pass.$salt);
            $query = "UPDATE tbl_users SET user_pass=:user_pass WHERE user_name=:user_name";
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":user_pass",$new_hashed_pass);
            $stmt -> bindParam(":user_name",$user_name);
            $stmt -> execute();

            $mail = new PHPMailer();
            $mail -> Subject = "یادآوری کلمه عبور";
            $mail -> Body = <<<EOS
<p style="direction:rtl; font-family: Tahoma;">
با سلام
<br/>
نام کاربری و کلمه ی عبور جدید شما:
<br/>
نام کاربری: $user_name<br/>
کلمه عبور جدید : $new_pass<br/>
<br/>
لطفا در اسرع وقت از پنل کاربری خود اقدام به تعویض رمز عبور خود نمایید. باتشکر.
</p>
EOS;
            $mail -> addAddress($user_email);
            $mail -> isHTML();
            $mail -> isSMTP();
            $mail -> CharSet = 'utf-8';
            $mail -> FromName = 'pdf-download';
            $mail -> SMTPAuth = true;
            $mail -> SMTPSecure = 'ssl';
            $mail -> Port = 465;
            $mail -> Host = 'smtp.gmail.com';
            $mail -> Username = EMAIL_USER_NAME;
            $mail -> Password = EMAIL_PASSWORD;

            //query 3 = INSERT MAIL TIME TO TBL_SENT_MAILS
            $send_time = time();
            $query = "INSERT INTO tbl_sent_mails(user_email,send_time) VALUES(:user_email,:send_time) ";
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":user_email",$user_email);
            $stmt -> bindParam(":send_time",$send_time);
            $stmt -> execute();

            //query 4 = DELETE OLD RECORDS FROM TBL_SENT_MAILS
            $query = "DELETE FROM tbl_sent_mails WHERE send_time < :send_time - 3600";
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":send_time",$send_time);
            $stmt -> execute();

            if($mail -> send()){
                $res = User::updateUserPassByEmail($new_hashed_pass , $user_email);
                if ($res)
                    return true;
                else
                    return false;
            }
            else
                return false;
        }
        else
            return false;
    }//ok

    public static function deleteUserById($user_id)
    {
        $ret = true;
        $user = User::getUserById($user_id);
        if ($user -> user_type_name != "admin")
        {
            $conn = self::connect();
            $query = "UPDATE tbl_posts SET user_id=1 WHERE user_id=:user_id";
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":user_id",$user_id);
            if(!$stmt -> execute())
                $ret = false;

            $query = "DELETE FROM tbl_users WHERE user_id=:user_id";
            $stmt = $conn -> prepare($query);
            $stmt -> bindParam(":user_id",$user_id);
            if(!$stmt -> execute())
                $ret = false;
        }
        else
            $ret = false;
        return $ret;
    }//ok

    public static function changeUserTypeById($user_id , $user_type_id)
    {
        $conn = self::connect();
        $query = "UPDATE tbl_users SET user_type_id=:user_type_id WHERE user_id=:user_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':user_type_id',$user_type_id);
        $stmt -> bindParam(':user_id',$user_id);
        $res = $stmt -> execute();
        if(!$res)
            $ret = false;
        else
            $ret = true;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getAllUserEmails()
    {
        $conn = self::connect();
        $query = "SELECT tbl_users.* , tbl_user_types.user_type AS user_type_name FROM tbl_users,tbl_user_types  WHERE tbl_user_types.user_type_id = tbl_users.user_type_id ORDER BY tbl_users.user_id ";
        $result = $conn -> query($query);
        if($result -> rowCount() != 0){
            $users = array();
            foreach ($result -> fetchAll(PDO::FETCH_ASSOC) as $row) {
                $users[] = new User($row);
            }
            $ret = $users;
        }else
            $ret = false;
        self::disconnect($conn);
        return $ret;
    }//ok

    public static function getUserAvatar($user_id){
        $conn = self::connect();
        $query = "SELECT user_avatar_path FROM tbl_users WHERE user_id = :user_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':user_id',$user_id);
        $stmt -> execute();
        if($stmt -> rowCount() > 0) {
            $row = $stmt -> fetch(PDO::FETCH_ASSOC)['user_avatar_path'];
        }else
            $row = false;
        self::disconnect($conn);
        return $row;
    }//ok

    public static function getUserAvatarByPostId($post_id){
        $conn = self::connect();
        $query = "SELECT user_avatar_path FROM tbl_users,tbl_posts
                      WHERE tbl_users.user_id=tbl_posts.user_id
                      AND tbl_posts.post_id = :post_id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':post_id',$post_id);
        $stmt -> execute();
        if($stmt -> rowCount() > 0) {
            $row = $stmt -> fetch(PDO::FETCH_ASSOC)['user_avatar_path'];
        }else
            $row = false;
        self::disconnect($conn);
        return $row;
    }//ok

    public static function updateUserAvatar($user_id , $new_path){
        $conn = self::connect();
        $query = "UPDATE tbl_users SET user_avatar_path = :path WHERE user_id=:id";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':path',$new_path);
        $stmt -> bindParam(':id',$user_id);
        $res = $stmt -> execute();
        self::disconnect($conn);
        if ($res)
            return true;
        else
            return false;
    }//ok

    public static function getUserCountsOfType($user_type_id)
    {
        $conn = self::connect();
        $query = "SELECT count(*) as cnt FROM tbl_users WHERE user_type_id = :user_type_id ";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':user_type_id',$user_type_id);
        $stmt -> execute();
        if($stmt -> rowCount() != 0){
            return (int)($stmt -> fetch(PDO::FETCH_ASSOC)['cnt']);
        }else
            return 0;
    }//ok

    public static function updateUserPassByEmail($user_pass , $user_email){
        $conn = self::connect();
        $query = "UPDATE tbl_users SET user_pass=:user_pass WHERE user_email=:user_email";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':user_pass',$user_pass);
        $stmt -> bindParam(':user_email',$user_email);
        $res = $stmt -> execute();
        self::disconnect($conn);
        if ($res)
            return true;
        else
            return false;
    }//ok

    public static function updateUserPassByUserName($user_pass , $user_name)
    {
        $conn = self::connect();
        $query = "UPDATE tbl_users SET user_pass=:user_pass WHERE user_name=:user_name";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':user_pass',$user_pass);
        $stmt -> bindParam(':user_name',$user_name);
        if ($stmt -> execute()){
            self::disconnect($conn);
            return true;
        }
        else{
            self::disconnect($conn);
            return false;
        }
    }//ok

    public static function setRandomHash($user_name , $random_hash)
    {
        $conn = self::connect();
        $query = "UPDATE tbl_users SET random_hash=:random_hash WHERE user_name=:user_name";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':random_hash',$random_hash);
        $stmt -> bindParam(':user_name',$user_name);
        self::disconnect($conn);
        $stmt -> execute();
    }//ok

    public static function getRandomHash($user_name)
    {
        $conn = self::connect();
        $query = "SELECT * FROM tbl_users WHERE user_name=:user_name";
        $stmt = $conn -> prepare($query);
        $stmt -> bindParam(':user_name',$user_name);
        $stmt -> execute();
        $result = $stmt -> fetch(PDO::FETCH_ASSOC)['random_hash'];
        self::disconnect($conn);
        return $result;
    }//ok

}