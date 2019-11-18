<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends MY_Model {
		protected $_table = 'users';
		protected $primary_key ='id';
		protected $return_type = 'array';
		public function __construct()
        {
                // Call the CI_Model constructor
                parent::__construct();
        }
		public function check_user($usernameParam, $passwordParam)
		{
			$this->load->model('UserSite_model');
			$sessionId =  $this->UserSite_model->getCurrentSessionId();
			$this->db->select('users.id, users.username, students.parentid, firstname, lastname, image as picture ,concat(class.name," ",section.name) as class, house.name as house, house.colour');
			$this->db->from('users');
			$this->db->join('students', 'students.id = users.usertypeid');
			$this->db->join('studentsclass', 'studentsclass.studentid = students.id');
			$this->db->join('csbjunction', 'csbjunction.id = studentsclass.csbid');
			$this->db->join('class', 'csbjunction.classid = class.id');
			$this->db->join('section', 'csbjunction.sectionid = section.id');
			$this->db->join('house', 'students.houseid = house.id');
			
			$this->db->where('users.username', $usernameParam);
			$this->db->where('users.password', md5($passwordParam));
			$this->db->where('users.status', STATUS_ACTIVE);
			$this->db->where('usertype',STUDENT_USER_TYPE);
			$this->db->where('studentsclass.sessionid', $sessionId);
			$this->db->where('studentsclass.iscurrent', IS_CURRENT);
			$query = $this->db->get();
			return ( $query->num_rows() > 0 ) ? $query->row(): NULL;
			
		}
		public function user_student_join()
		{
			$this->load->model('UserSite_model');
			$sessionId =  $this->UserSite_model->getCurrentSessionId();
			$this->db->select('users.id, users.username, students.parentid, firstname, lastname, image as picture');
			$this->db->join('students', 'students.id = users.usertypeid');
			return $this;
		}
		public function generateCode($usernameParam)
		{
			$code = mt_rand(100000, 999999);
			$data = array(
				'code' => $code,
				'codetime' => date('Y-m-d H:i:s')
				);
			
			$this->db->where('username', $usernameParam);
			$this->db->where('status', 'active');
			$this->db->where('usertype','student');
			$this->db->update('users', $data); 
			return $code;
		}
		//TODO for now removing access token and gcm
		public function removeAccessToken($usernameParam, $imeiParam)
		{
				$this->db->select('users.usertypeid');
				$this->db->from('users');
				$this->db->where('status', 'active');
				$this->db->where('username',$usernameParam);
				$users =  $this->db->get()->result();
				$data = array(
				'accesstoken' => null,
				'gcmregistrationid' => null
				);
				$this->db->where('userid', $users[0]->usertypeid);
				$this->db->where('deviceimei', $imeiParam);
				$this->db->update('usersloginlogs', $data); 
		}
		//check login logs, if exists for a device then update, otherwise insert
		public function upsertLoginLogs($userid, $imei, $token)
		{
			$this->db->select('id');
			$this->db->from('usersloginlogs');
			$this->db->where('userid', $userid);
			$this->db->where('deviceimei',$imei);
			
			if($this->db->count_all_results() == 0)
			{
				
				$data = array(
				'userid' => $userid,
				'accesstoken' => $token,
				'lastlogin' => date('Y-m-d H:i:s'),
				'deviceimei' => $imei
				);
				$data1 = array(
				'userid' => $userid,
				'deviceimei' => $imei
				);
				//to avoid multiple insert issues
				$this->db->delete('usersloginlogs', $data1); 
				$this->db->insert('usersloginlogs', $data); 
			}
			else{
				$data = array(
				'accesstoken' => $token,
				'lastlogin' => date('Y-m-d H:i:s'),
				);
				$this->db->where('userid', $userid);
				$this->db->where('deviceimei', $imei);
				$this->db->update('usersloginlogs', $data); 
			}
		}
		public function updateGCMRegistrationID($userid, $imei, $tokenParam, $registrationId)
		{
			$data = array( 'gcmregistrationid' => $registrationId);
			$this->db->where('userid', $userid);
			$this->db->where('deviceimei', $imei);
			$this->db->where('accesstoken', $tokenParam);
			$this->db->update('usersloginlogs', $data); 
		}
		public function updatePassword($username, $password,$fromWeb = 0)
		{
			$this->db->select('users.id');
			$this->db->from('users');
			$this->db->where('status', 'active');
			$this->db->where('username', $username);
			$users =  $this->db->get()->result();
			
			$data1 = array('accesstoken'=>null);
			//TODO IF IN USERS LOGIN LOGS STAFF IS ALSO MAINTAINED
			$this->db->where('userid', $users[0]->id);
			$this->db->update('usersloginlogs', $data1);
			
			$data = array( 'password' => md5($password)
						  ,'code'=>null);
			$this->db->where('username', $username);
			$this->db->where('status','active');
			$this->db->where('usertype','student');
			$this->db->update('users', $data);
			if($fromWeb == 1) {
				return $this->db->affected_rows();
			}
		}
		public function getAllChild($userID, $parentID, $fromWeb = 0)
		{
			$this->load->model('UserSite_model');
			$sessionId =  $this->UserSite_model->getCurrentSessionId();
			$this->db->select('username,firstname, lastname, image as picture,concat(class.name," ",section.name) as class, house.name as house, house.colour');
			$this->db->from('users');
			$this->db->join('students', 'students.id = users.usertypeid');
			$this->db->join('house', 'students.houseid = house.id');
			$this->db->join('studentsclass', 'studentsclass.studentid = students.id');
			$this->db->join('csbjunction', 'csbjunction.id = studentsclass.csbid');
			$this->db->join('class', 'csbjunction.classid = class.id');
			$this->db->join('section', 'csbjunction.sectionid = section.id');
			$this->db->where('studentsclass.sessionid', $sessionId);
			$this->db->where('studentsclass.iscurrent', IS_CURRENT);
			$this->db->where('parentid', $parentID);
			if($fromWeb == 0)
			{
				$this->db->where('users.id !=',$userID);
			}
			$this->db->where('status', 'active');
			$this->db->where('usertype','student');
			return $this->db->get()->result();
		}
		public function getAllSiblings($userDetails, $sessionId)
		{
			$this->db->select('username,students.id, CT.branchid,ST.csbid');
			$this->db->from('users');
			$this->db->join('students', 'students.id = users.usertypeid');
			$this->db->join(Utility::$studentclass_table .' AS ST', 'students.id = ST.studentid');
			$this->db->join(Utility::$csbjunction_table.' AS CT', 'ST.csbid = CT.id');
			$this->db->where('ST.sessionid', $sessionId);
			$this->db->where('ST.iscurrent', IS_CURRENT);	
			$this->db->where('parentid',$userDetails->parentid);
			$this->db->where('status', 'active');
			$this->db->where('usertype','student');
			return $this->db->get()->result();
		}
		public function validateUsername($username, $fromweb = 0)
		{
			$this->db->select('username');
			$this->db->from('users');
			$this->db->where('users.username', $username);
			$this->db->where('status', 'active');
			if($fromweb == 0) {
				$this->db->where('usertype','student');
			}
			$num =$this->db->get()->num_rows();
			return $num;
		}
		public function validateCode($username, $code)
		{
			$this->db->select('username');
			$this->db->from('users');
			$this->db->where('users.username', $username);
			$this->db->where('code', $code);
			$this->db->where('codetime >','DATE_SUB(NOW(), INTERVAL 30 MINUTE)', false);
			$num =$this->db->get()->num_rows();
			return $num;
		}
		public function validateToken($username, $token, $imei)
		{
			$this->db->select('username');
			$this->db->from('users');
			$this->db->join('usersloginlogs ul', 'ul.userid = users.id');
			$this->db->where('users.username', $username);
			$this->db->where('ul.accesstoken', $token);
			$this->db->where('ul.deviceimei', $imei);
			$this->db->where('ul.lastlogin >','DATE_SUB(NOW(), INTERVAL 30 MINUTE)', false);
			
			$num =$this->db->get()->num_rows();
			
			return $num;
		}
}