<?php

require_once __DIR__.'/../lib/user.php';
require_once __DIR__.'/../lib/article.php';
$pdo = require_once __DIR__.'/../lib/db.php';

class Restful
{
	private $_user;
	private $_article;
	private $_requestMethod;
	private $_resourceName;
	private $_id; //文章id

	/**
	 * @var array 允许请求的资源
	 */
	private $_allowResources = ['users','articles'];

	/**
	 * @var array 允许请求的方法
	 */
	private $_allowRequestMethods = ['GET','POST','DELETE','PUT','OPTIONS'];

	/**
	 * @var array 状态码
	 */
	private $_statusCodes = [
		200 => 'OK',
		204 => 'No Content',
		400 => 'Bad request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		500 => 'Server Internal Error'
	];

	public function __construct(User $_user, Article $_article)
	{
		$this->_user = $_user;
		$this->_article = $_article;
	}

    public function run()
    {
    	try{
    		$this->_setRequestMethod();
    		$this->_setResource();

            if($this->_resourceName == 'users'){
                $this->_json($this->_handleUser());
            }else{
                $this->_json($this->_handleArticle());
            }
    	}catch(Exception $e){
    		$this->_json(['error'=>$e->getMessage()], $e->getCode());
    	}	
    }

    private function _json($arr,$code=0)
    {  	
        if($arr === null && $code === 0){
            $code = 204;
        }
        if($arr !== null && $code === 0){
            $code = 200;
        }
    	if($code>0 && $code!=200 && $code !=204){
    		header('HTTP/1.1 ' . $code . ' ' . $this->_statusCodes[$code]);
    	}
    	header('Content-Type:application/json;charset=utf-8');

        if($arr !==null){
            echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        }
    	exit();
    }

    /*初始化请求方法*/
    private function _setRequestMethod()
    {
    	$this->_requestMethod = $_SERVER['REQUEST_METHOD'];
    	if(!in_array($this->_requestMethod, $this->_allowRequestMethods)){
    		throw new Exception("请求方法不被允许", 405);		
    	}
    }

    /*初始化请求资源*/
    private function _setResource()
    {
    	$path = $_SERVER['PATH_INFO'];
    	$params = explode('/',$path);
    	$this->_resourceName = $params[1];
    	//print_r($params);
    	if(!in_array($this->_resourceName, $this->_allowResources)){
    		throw new Exception("请求资源不被允许", 400);		
    	}
    	if(!empty($params[2])){
    		$this->_id= $params[2];
    	}
    }

    /*请求用户*/
    private function _handleUser(){
        
        if($this->_requestMethod != 'POST'){
            throw new Exception("请求方法不被允许", 405);
        }
        
        $body = $this->_getBodyParams();
        if(empty($body['username'])){
            throw new Exception("用户名不能为空", 400);
        }
        if(empty($body['password'])){
            throw new Exception("密码不能为空", 400);    
        }

        return $this->_user->register($body['username'],$body['password']);
    }

    /*请求文章资源*/
    private function _handleArticle(){
        switch ($this->_requestMethod) {
            case 'POST':
                return $this->_handleArticleCreate();
                break;
            case 'PUT':
                return $this->_handleArticleEdit();
                break;
            case 'GET':
                if(empty($this->_id)){
                    return $this->_handleArticleList();
                }else{
                    return $this->_handleArticleView();
                }
                break;
            case 'DELETE':
                return $this->_handleArticleDel();
                break;
            default:
                throw new Exception("请求方法不被允许", 405);
                break;
        }
    }

    /*获取请求参数*/
    private function _getBodyParams(){
        $raw = file_get_contents('php://input');
        if(empty($raw)){
            throw new Exception("请求参数错误", 400);
        }
        return json_decode($raw, true);
    }

    /*
    * 创建文章
    * @return array
    */
    private function _handleArticleCreate(){
        $body = $this->_getBodyParams();
        if(empty($body['title'])){
            throw new Exception("文章标题不能为空", ErrorCode::TITLE_CANNOT_EMPTY);
        }
        if(empty($body['content'])){
            throw new Exception("文章内容不能为空", ErrorCode::CONTENT_CANNOT_EMPTY);      
        }

        $user = $this->_userLogin($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

        try {
            $article = $this->_article->create($body['title'],$body['content'],$user['user_id']);
            return $article;
        } catch (Exception $e) {
            if(in_array($e->getCode(), 
                [
                    ErrorCode::TITLE_CANNOT_EMPTY,
                    ErrorCode::CONTENT_CANNOT_EMPTY,

                ])){
                throw new Exception($e->getMessage(), 400);
            }
            throw new Exception($e->getMessage(), 500);
        }
    }

    /*
    * 编辑文章
    * @return array
    */
    private function _handleArticleEdit(){
        $user = $this->_userLogin($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

        try {
            $article = $this->_article->read($this->_id);
            if($article['uid'] != $user["user_id"]){
                throw new Exception("你无权编辑", 403);
            }

            $body = $this->_getBodyParams();
            $title = empty($body['title'])?$article['title']:$body['title'];
            $content = empty($body['content'])?$article['content']:$body['content'];
            if($title == $article['title'] && $content == $article['content']){
                return $article;
            }

            return $this->_article->edit($title,$content,$user["user_id"],$article['aid']);
        } catch (Exception $e) {
            if($e->getCode() < 100){
                if($e->getCode() == ErrorCode::ARTICLE_NOT_FOUND){
                    throw new Exception($e->getMessage(), 404);
                }
                throw new Exception($e->getMessage(), 400);
            }else{
                throw $e;              
            }
        }
    }

    /*文章列表*/
    private function _handleArticleList(){
        $user = $this->_userLogin($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);
        $page = isset($_GET['page'])?$_GET['page']:1;
        $size = isset($_GET['size'])?$_GET['size']:5;

        try {
            return $this->_article->articlePage($user['user_id'],$page,$size);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 500);
        }   
    }

    /*查看文章*/
    private function _handleArticleView(){
        try {
            return $this->_article->read($this->_id);
        } catch (Exception $e) {
            if($e->getCode() == ErrorCode::ARTICLE_NOT_FOUND){
                throw new Exception($e->getMessage(), 404);
            }else{
                throw new Exception($e->getMessage(), 500);
            }
        }   
    }

    /*文章删除*/
    private function _handleArticleDel(){
        $user = $this->_userLogin($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

        try {
            $article = $this->_article->read($this->_id);
            if($article['uid'] != $user["user_id"]){
                throw new Exception("你无权编辑", 403);
            }
            $this->_article->del($article['aid'],$user["user_id"]);
            return null;
        } catch (Exception $e) {
            if($e->getCode() < 100){
                if($e->getCode() == ErrorCode::ARTICLE_NOT_FOUND){
                    throw new Exception($e->getMessage(), 404);
                }
                throw new Exception($e->getMessage(), 400);
            }else{
                throw $e;              
            }
        }
    }

    /*用户登录 */
    private function _userLogin($PHP_AUTH_USER,$PHP_AUTH_PW){
        try {
            return $this->_user->login($PHP_AUTH_USER,$PHP_AUTH_PW);
        } catch (Exception $e) {
            if(in_array($e->getCode(), 
                [
                    ErrorCode::USERNAME_CANNOT_EMPTY,
                    ErrorCode::PASSWORD_CANNOT_EMPTY,
                    ErrorCode::USERNAME_OR_PASSWORD
                ])){
                throw new Exception($e->getMessage(),400);
            }
            throw new Exception($e->getMessage(),500);
        }      
    }

}

$user = new User($pdo);
$article = new Article($pdo);

$restful = new Restful($user,$article);
$restful->run();