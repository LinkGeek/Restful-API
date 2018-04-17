<?php

/*
* pdo连接数据库
* User: Giant
* Date: 2018-4-16
*/

$dbms='mysql';     //数据库类型
$host='localhost'; //数据库主机名
$dbName='imooc_restful_api';    //使用的数据库
$user='root';      //数据库连接用户名
$pass='rootroot';          //对应的密码
$dsn="$dbms:host=$host;dbname=$dbName";


try {
    $pdo = new PDO($dsn, $user, $pass); //初始化一个PDO对象
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);//原样数据格式输出
} catch (PDOException $e) {
    die ("Error!: " . $e->getMessage() . "<br/>");
}
return $pdo;