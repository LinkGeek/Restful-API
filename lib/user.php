<?php

/*
 * User: hehzan
 * Date: 2018-4-3
 */

require_once __DIR__.'/code.php';
class User
{
	/*数据库连接句柄*/
	private $_db;

	public function __construct($_db)
	{
		$this->_db = $_db;
	}

	/*
	 * 用户登录
	 */
	public function login($uname,$pwd)
	{
		if(empty($uname)){
			throw new Exception("用户名不能为空", ErrorCode::USERNAME_CANNOT_EMPTY);			
		}
		if(empty($pwd)){
			throw new Exception("密码不能为空", ErrorCode::PASSWORD_CANNOT_EMPTY);	
		}
		$sql ="SELECT * FROM `user` WHERE `uname`=:uname AND `pwd`=:pwd";
		$stmt = $this->_db->prepare($sql);
		$stmt->bindParam(':uname',$uname);
		$stmt->bindParam(':pwd',$this->_md5($pwd));

		if(!$stmt->execute()){
			throw new Exception("服务器出错", ErrorCode::SERVER_ERROR);
		}
		$user = $stmt->fetch(PDO::FETCH_ASSOC);
		if(empty($user)){
			throw new Exception("用户名或密码错误", ErrorCode::USERNAME_OR_PASSWORD);
		}
		unset($user['pwd']);
		return $user;
	}

	/*
	 * 用户注册
	 */
	public function register($uname,$pwd)
	{
		if(empty($uname))
		{
			throw new Exception("用户名不能为空", ErrorCode::USERNAME_CANNOT_EMPTY);			
		}

		if(empty($pwd))
		{
			throw new Exception("密码不能为空", ErrorCode::PASSWORD_CANNOT_EMPTY);	
		}

		if($this->_isUsernameExists($uname))
		{
			throw new Exception("用户已存在", ErrorCode::USERNAME_EXISTS);			
		}	

		//写入数据库
		$sql ="INSERT INTO user (uname,pwd,create_time) VALUES (:uname, :pwd, :create_time)";

		$create = time();
		$stmt = $this->_db->prepare($sql);
		$stmt->bindParam(':uname',$uname);
		$stmt->bindParam(':pwd',$this->_md5($pwd));
		$stmt->bindParam(':create_time',$create);

		if(!$stmt->execute()){
			throw new Exception("注册失败", ErrorCode::REGISTER_FAIL);
		}

		return [
			'code'=>200,
			'msg'=>'ok',
			'userId'=>$this->_db->lastInsertId(),
			'uname'=>$uname,
			'create_time'=>$create
		];
	}

	/*
	 *  加密
	 */
	private function _md5($str,$key='sdhbsfgsfg')
	{
 		return md5($str.$key);
	}

	/*
	 * 检测用户名是否存在
	 */
	private function _isUsernameExists($uname)
	{
		$sql ="SELECT * FROM `user` WHERE `uname`=:uname";
		$stmt = $this->_db->prepare($sql);
		$stmt->bindParam(":uname",$uname);
		$stmt->execute();
		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		return !empty($result);
	}

	


}