<?php

defined('BASEPATH') OR exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
require APPPATH . '/libraries/REST_Controller.php';

/**
 * This is an example of a few basic user interaction methods you could use
 * all done with a hardcoded array
 *
 * @package         CodeIgniter
 * @subpackage      Rest Server
 * @category        Controller
 * @author          Phil Sturgeon, Chris Kacerguis
 * @license         MIT
 * @link            https://github.com/chriskacerguis/codeigniter-restserver
 */
class V1 extends REST_Controller {
	function __construct()
    {
        // Construct the parent class
        parent::__construct();
		$this->load->model('User_model');
    }
   
   public function login_post()
   {
		$usernameParam = $this->post('username');
		$passwordParam = $this->post('password');
		$imeiParam = $this->post('imei');
		$isError =  false;
		if($usernameParam !== NULL && $passwordParam !== NULL && $imeiParam !== NULL)
		{
			$user = $this->User_model->check_user($usernameParam, $passwordParam );
			if($user != null)
			{
				//update token and imei number in database
				$token = md5(uniqid(rand(), true));
				//check if entry exists for particular deviceimei, if yes, then update otherwise insert
				$this->User_model->upsertLoginLogs($user->id, $imeiParam, $token);
				$allChildData = $this->User_model->getAllChild($user->id, $user->parentid);
				
				$currentUser = array();
				$currentUser["username"] = $user->username;
				$currentUser["firstname"] = $user->firstname;
				$currentUser["lastname"] = $user->lastname;
				$currentUser["picture"] = $user->picture;
				$currentUser["class"] = $user->class;
				$currentUser["house"] = $user->house;
				$currentUser["colour"] = $user->colour;
				array_unshift($allChildData , $currentUser);
				$this->set_response([
					'status' => TRUE,
					'result' => array('token'=>$token,'users'=>$allChildData)
				], REST_Controller::HTTP_OK); 
			}
			else
			{
				$this->_unauthorizedRequest();	
			}	
		}
		else
		{
			$isError = true;
		}
		if($isError)
		{
			$this->_notAcceptableRequest();
		}	
   }
   public function logout_post(){
	   $headers = apache_request_headers();
	   $usernameParam = $this->post('username');
	   if(isset($headers['Authorization']))
	   {
		$tokenParam = $headers['Authorization'];
	   }
	   else
	   {
		$tokenParam = null;
	   }
	   $imeiParam = $this->post('imei');
	   $currentUsernameParam = $this->post('currentUsername');
	   
		if($this->_validatedCommonParam($usernameParam, $tokenParam, $imeiParam, $currentUsernameParam  ))
		{
		   $isValidToken = $this->_validateToken($usernameParam, $tokenParam, $imeiParam);
		   $isError = false;
		   if($isValidToken)
		   {
				$isValidSibling = $this->_validateSiblings($usernameParam, $currentUsernameParam);
				if($isValidSibling)
				 {
					$result = $this->User_model->removeAccessToken($usernameParam, $imeiParam, $tokenParam);
					$this->set_response([
					'status' => TRUE
					], REST_Controller::HTTP_OK); 
				 }
				 else
				{
					$isError = true;
				}
			
		   }
		   else
		   {
				$isError = true;
		   }
		   if($isError)
		   {
				$this->_unauthorizedRequest();	
		   }
		}
		else
		{
			$this->_notAcceptableRequest();
		}
	   
   }
   public function validateCode_post(){
	   $usernameParam = $this->post('username');
	   $imeiParam = $this->post('imei');
	   $code = $this->post('code');
	   $password = $this->post('password');
	   //TODO VALIDATE IMEI
	   if($usernameParam != null && $imeiParam != null && $code != null)
		{
		   $isValidToken = $this->User_model->validateUsername($usernameParam);
		   $isError = false;
		   if($isValidToken)
		   {
				if($this->User_model->validateCode($usernameParam, $code))
				{
					$this->set_response([
					'status' => TRUE
					], REST_Controller::HTTP_OK); 
				}
				else{
					$this->set_response([
					'status' => FALSE
					], REST_Controller::HTTP_OK); 
				}
		   }
		   else
		   {
				$isError = true;
		   }
		   if($isError)
		   {
				$this->_unauthorizedRequest();	
		   }
		}
		else
		{
			$this->_notAcceptableRequest();
		}
	   
   }
   public function reset_post(){
	   
	   $usernameParam = $this->post('username');
	   $imeiParam = $this->post('imei');
	   $code = $this->post('code');
	   $password = $this->post('password');
	   //TODO VALIDATE IMEI
	   if($usernameParam != null && $imeiParam != null && $code != null && $password !=null)
		{
		   $isValidToken = $this->User_model->validateUsername($usernameParam);
		   $isError = false;
		   if($isValidToken)
		   {
				if($this->User_model->validateCode($usernameParam, $code))
				{
					//update password
					$this->User_model->updatePassword($usernameParam, $password);
					$this->set_response([
					'status' => TRUE
					], REST_Controller::HTTP_OK); 
				}
				else{
					$this->set_response([
					'status' => FALSE
					], REST_Controller::HTTP_OK); 
				}
		   }
		   else
		   {
				$isError = true;
		   }
		   if($isError)
		   {
				$this->_unauthorizedRequest();	
		   }
		}
		else
		{
			$this->_notAcceptableRequest();
		}
	   
   }
   
   public function notices_post()
    {
	   $headers = apache_request_headers();
	   $usernameParam = $this->post('username');
	   if(isset($headers['Authorization']))
	   {
		$tokenParam = $headers['Authorization'];
	   }
	   else
	   {
		$tokenParam = null;
	   }
	   $imeiParam = $this->post('imei');
	   $currentUsernameParam = $this->post('currentUsername');
	   $limitParam = $this->post('limit');
	   $offsetParam = $this->post('offset');
	   
	   if($this->_validatedCommonParamSecond($usernameParam, $tokenParam, $imeiParam, $currentUsernameParam, $limitParam, $offsetParam))
		{
		   $isValidToken = $this->_validateToken($usernameParam, $tokenParam, $imeiParam);
		   $isError = false;
		   if($isValidToken)
		   {
				$isValidSibling = $this->_validateSiblings($usernameParam, $currentUsernameParam);
				if($isValidSibling)
				 {
					//get notice
					//union in mysql
					$searchParam = $this->post('search');
					//if search keyword  not equal to null
					$caloffsetParam = $offsetParam * $limitParam;
					$notices = $this->User_model->getNotice($currentUsernameParam, $searchParam, $limitParam, $caloffsetParam);
					foreach($notices as $n){
						unset($n->originaldate);
					}
					$this->set_response([
					'status' => TRUE,
					'result' => array('notices' => $notices),
					'limit'=>$limitParam,
					'offset'=>$offsetParam
					], REST_Controller::HTTP_OK); 
				 }
				 else
				{
					$isError = true;
				}
			
		   }
		   else
		   {
				$isError = true;
		   }
		   if($isError)
		   {
				$this->_unauthorizedRequest();	
		   }
		}
		else
		{
			$this->_notAcceptableRequest();
		}
	}
   
   private function _switchUser($userDetails, $type , $id ,$type_id, $sessionId, $fromSource)
   {
		$switchUser = false;
		$switchUserName = null;
		
		$allSiblings = $this->User_model->getAllSiblings($userDetails, $sessionId);
		
		if(count($allSiblings) > 1)
		{
			$userIdArray = array();
			foreach($allSiblings as $row)
			{
				array_push($userIdArray, $row->id);
			}
			
			$record = $this->User_model->getForWhichUser($type_id, $id, $userIdArray, $sessionId);
			if(count($record))
			{
				foreach($allSiblings as $row)
				{
					if($record[0]->userid == $row->id)
					{
						//IF UNREAD
						if($record[0]->isread == 0)
						{
							$this->User_model->updateReadStatusClone($row->id, $type_id, $id);
						}
						if(!$fromSource)
						{
							$switchUserName = $row->username;
							$switchUserBranch = $row->branchid;
							$switchUserClass = $row->csbid;
							
						}
						else
						{
							$answer['switchUser'] = $switchUser;
							$answer['switchUserName'] = $switchUserName;
							return $answer;
						}
						break;
					}
				}
			}
			else
			{
				$answer['switchUser'] = $switchUser;
				$answer['switchUserName'] = $switchUserName;
				return $answer;
			}
		}
		else
		{
			$this->User_model->updateReadStatusClone($userDetails->id, $type_id, $id);
			$answer['switchUser'] = $switchUser;
			$answer['switchUserName'] = $switchUserName;
			return $answer;
			
		}
		if($switchUserName == $userDetails->username)
		{
			$switchUserName = null;
			$answer['switchUser'] = $switchUser;
			$answer['switchUserName'] = $switchUserName;
			return $answer;
		}
		
		if(!$fromSource && $type == TYPE_CLASS && $userDetails->csbid != $switchUserClass && $switchUserName != null)
		{
			$switchUser = true;
		}
		else if(!$fromSource && $type == TYPE_BRANCH && $userDetails->branchid != $switchUserBranch && $switchUserName != null)
		{
			$switchUser = true;
		}
		else
		{
			$switchUser = false;
			$switchUserName = null;
		}
		$answer['switchUser'] = $switchUser;
		$answer['switchUserName'] = $switchUserName;
		return $answer;
   }
   private function _validateToken($username, $token, $imei)
   {
		//$this->load->config('rest_custom');
		$count = $this->User_model->validateToken($username, $token, $imei);
		if($count >= 1)
		{
			return true;
		}
		else
		{
			return false;
		}
   }
   
   private function _sendmail($template, $params)
   {
		$this->load->model('UserSite_model');
	   $sessionId =  $this->UserSite_model->getCurrentSessionId();
	   $userInfo = $this->User_model->getUserInfo($params['username'],$sessionId);
	   $result = $this->User_model->getTemplate($template, $userInfo->branchid);
	   $name =  $userInfo->firstname.' '.$userInfo->lastname;
	   $description = str_replace( '<name>', $name,$result->description);
	   $description = str_replace( '<code>', $params['code'],$description);
	   $headers  = 'MIME-Version: 1.0' . "\r\n";
	   $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
	   $headers .= 'From: SchoolDigiApp <admin@schooldigiapp.com>' . "\r\n";
	   mail($userInfo->email, $result->subject, $description, $headers );
   }

}