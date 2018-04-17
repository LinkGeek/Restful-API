<?php

/*

*/
require_once __DIR__.'/code.php';

class Article
{
	/*数据库连接句柄*/
	private $_db;

	public function __construct($_db)
	{
		$this->_db = $_db;
	}


	/*发表文章*/
	public function create($title,$content,$userId)
	{
		if(empty($title)){
			throw new Exception("文章标题不能为空", ErrorCode::TITLE_CANNOT_EMPTY);
		}

		if(empty($content)){
			throw new Exception("文章内容不能为空", ErrorCode::CONTENT_CANNOT_EMPTY);	
		}

		//写入数据库
		$sql = "INSERT INTO article (title,content,uid,create_time) VALUES (:title,:content,:uid,:create_time)";
		$create = time();
		$stmt = $this->_db->prepare($sql);
		$stmt->bindParam(':title',$title);
		$stmt->bindParam(':content',$content);
		$stmt->bindParam(':uid',$userId);
		$stmt->bindParam(':create_time',$create);
		
		if(!$stmt->execute()){
			throw new Exception("添加文章失败", ErrorCode::ARTICLE_CREATE_FAIL);
		}

		return [
			'code'=>200,
			'msg'=>'ok',
			'aid'=>$this->_db->lastInsertId(),
			'title'=>$title,
			'content'=>$content,
			'create_time'=>$create
		];
	}

	/*编辑文章*/
	public function edit($title,$content,$userId,$aid)
	{
		$article = $this->read($aid);
		if($article['uid'] != $userId){
			throw new Exception("你无权编辑该文章", ErrorCode::USERNAME_CANNOT_EMPTY);			
		}

		$title = !empty($title) ? $title : $article['title'];
		$content = empty($content) ? $article['content'] : $content;

		//写入数据库
		$sql = "UPDATE article SET `title`=:title,`content`=:content WHERE `aid`=:aid";
		$stmt = $this->_db->prepare($sql);
		$stmt->bindParam(':title',$title);
		$stmt->bindParam(':content',$content);
		$stmt->bindParam(':aid',$aid);

		if(!$stmt->execute()){
			throw new Exception("编辑文章失败", 402);
		}

		return [
			'code'=>200,
			'msg'=>'ok',
			'title'=>$title,
			'content'=>$content,
		];
	}

	/*查询文章*/
	public function read($aid)
	{
		if(empty($aid)){
			throw new Exception("文章id不能为空", ErrorCode::PASSWORD_CANNOT_EMPTY);	
		}

		$sql = "SELECT * FROM article WHERE aid=:aid";
		$stmt = $this->_db->prepare($sql);
		$stmt->bindParam(':aid',$aid);
		$stmt->execute();
		$art = $stmt->fetch(PDO::FETCH_ASSOC);
		if(empty($art)){
			throw new Exception("文章不存在", ErrorCode::ARTICLE_NOT_FOUND);
		}
		return $art;
	}

	/*删除文章*/
	public function del($aid,$userId)
	{
		$article = $this->read($aid);
		if($article['uid'] != $userId){
			throw new Exception("你无权操作", ErrorCode::USERNAME_CANNOT_EMPTY);			
		}
		$sql = "DELETE FROM article WHERE aid=:aid AND uid=:uid";
		$stmt = $this->_db->prepare($sql);
		$stmt->bindParam(':uid',$userId);
		$stmt->bindParam(':aid',$aid);

		if($stmt->execute() ===false){
			throw new Exception("删除失败", ErrorCode::USERNAME_CANNOT_EMPTY);
		}
		return true;
	}

	/*文章分页*/
	public function articlePage($userId,$page=1,$size=5){
		if($size>50){
			throw new Exception("分页大小最大为50", ErrorCode::USERNAME_CANNOT_EMPTY);	
		}

		$sql = "SELECT * FROM article WHERE uid=:uid LIMIT :offset,:size";
		$offset = ($page-1)*$size;
		$offset = $offset < 0 ? 0 : $offset;
		$stmt = $this->_db->prepare($sql);
		$stmt->bindParam(':uid',$userId);
		$stmt->bindParam(':offset',$offset);
		$stmt->bindParam(':size',$size);
		$stmt->execute();
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		return $data;
	}
}