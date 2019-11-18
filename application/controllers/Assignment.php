<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Assignment extends CI_Controller {

	function __construct()
    {
        // Construct the parent class
        parent::__construct();
		$this->template->set_layout(USER_LAYOUT);
		$this->data['css'] = 'skin-blue-light sidebar-mini';
		$this->load->model(array('assignment_model','cssjunction_model','setting_model'));
		$this->permissionArray = get_permission();
		$this->breadcrumbs->push(lang('assignment_menu'), '');
		$this->userData = $this->session->userdata('userinfo');
		
    }
	public function index()
	{
			if(!checkSession())
			{
				loginPageURL();
			}
			$userData = $this->session->userdata('userinfo'); 
			if(!in_array(VIEW_ASSIGNMENT_PERMISSION, $this->permissionArray))
		    {
			  dashboardURL();
		    }
			if( isset($_POST['start']) && isset($_POST['length']))
			{
				$columns = array('id','title','type','subjectname','class_branch_section_name','fromdate_f','todate_f','status','action');
				$searchArray = array();
				if( !empty($_POST['search']['value']) ) {   
					$searchArray['q'] = $_POST['search']['value'];
				}
				$order = $_POST['order'][0]['dir'];
				//Get data from model
				$assignmentList = $this->assignment_model->getAssignmentList($userData['academicyearid'],$searchArray,(int)$_POST['length'],(int)$_POST['start'], $columns[$_POST['order'][0]['column']], $order);
				
				$this->tableData = array();
				
				foreach($assignmentList['records'] as $row) {  
					$nestedData=array(); 

					$nestedData[] = $row->id;
					$nestedData[] = $row->title;
					$nestedData[] = $row->type;
					$nestedData[] = $row->subjectname;
					$nestedData[] = $row->class_branch_section_name;
					$nestedData[] = $row->fromdate_f;
					$nestedData[] = $row->todate_f; 
					$nestedData[] = $row->status;
					$nestedData[] = "";
					
					$this->tableData[] = $nestedData;
				}
				////////////Pagination///////////////////
				$filteredCount = "";
				if(!empty($searchArray['q']))
				{
					$filteredCount = $assignmentList['total_records'];
				}
				$assignmentList = $this->assignment_model->getAssignmentList($userData['academicyearid']);
				//print_r( $subjectList);
				$count = $assignmentList['total_records'];
				if(empty($searchArray['q'])){ $filteredCount = $count;}
				////////////Send Data /////////////
				$json_data = array(
					"draw"            => intval( $_REQUEST['draw'] ),
					"recordsTotal"    => intval($count ),
					"recordsFiltered" => intval( $filteredCount),
					"data"            => $this->tableData
				);
				echo json_encode($json_data);
				return;
			}
			$this->data['userinfo'] = $userData;
			$this->template->add_js('assignment.js');
			$this->template->title( lang('assignment_title'));
			$this->template->build('assignment/list', $this->data);
		
	}
	public function form( $assignment_id = "")
	{
			if(!checkSession())
			{
				loginPageURL();
			}
			$userData = $this->session->userdata('userinfo');
			if(!in_array(ADD_ASSIGNMENT_PERMISSION, $this->permissionArray))
		    {
			  dashboardURL();
		    }

			$this->data['sessionDate'] = get_session_end_date();

			 ////////Get Subject Details///////
			$this->data['btnText'] = lang('btn_save');
			$selectedCsbids = $selectedSubject="";
			$hasEditMode = false;
			if( $assignment_id > 0 ){
				$assignment_info = $this->assignment_model->getAssignmentDetails( $userData['academicyearid'],$assignment_id );
				
				if(!count( $assignment_info))
				{
				  dashboardURL();
				}else{
					$hasEditMode = true;
				}
				if( ( $assignment_info->fromdate && $assignment_info->fromdate!=DATETIME_NULL)  && (  $assignment_info->todate && $assignment_info->todate!=DATETIME_NULL )){ 
					$assignment_info->assignmentdate = date(ASSIGNMENT_DATE_FORMAT_FOR_DISPLAY,strtotime($assignment_info->fromdate)).' - '.date(ASSIGNMENT_DATE_FORMAT_FOR_DISPLAY,strtotime($assignment_info->todate));
				}else{
					$assignment_info->assignmentdate = "";
				}
				///////////Get Selected Values//////////////
				$dataCssJunction = $this->assignment_model->getSelectedCssJunction( $userData['academicyearid'],$assignment_info->cssid);
				
				if( $dataCssJunction ){
					$selectedSubject = $assignment_info->cssid;
					$selectedCsbids = $dataCssJunction['selectedCsbids'];
				}
				$this->data['btnText'] = lang('btn_update');
				$this->data['assignment_info'] = $assignment_info;
			}
			
			$this->data['selectedCsbids'] = $selectedCsbids;
			
			//////////Get Class section//////
			$classSectionList = $this->cssjunction_model->getSectionList( $userData['userid'],$userData['academicyearid'] );
			$dd_list_csb = array(''=>'Select'); 
			foreach($classSectionList as $section){
				$dd_list_csb["{$section->id}"] = $section->class_branch_section_name;
			}  
			$this->data['dd_list_csb'] = $dd_list_csb;
			
			//////////////GET Subject dropdown
			$this->data['dd_cssjunction_subject'] = Utility::getCssJunctionDropdown(array('csb_ids'=>$selectedCsbids,'subject_ids'=>$selectedSubject));
			
			$token = md5(uniqid(rand(), true));
			$this->session->set_userdata('token',$token);
			$this->data['token'] = $token;
			$this->data['page'] = lang('assignment_add');
			$this->data['userinfo'] = $userData;
			$this->template->add_js('assignment.js');
			$this->data['hasEditMode'] = $hasEditMode;
			$this->data['assignment_id'] = $assignment_id;
			$title = $this->data['page_title'] = ( $assignment_id > 0 )  ? lang('edit_assignment'): lang('add_assignment');
			$this->template->title( $title ); 
			$this->breadcrumbs->push($title, lang('assignment_add'));
			$this->template->build('assignment/form', $this->data);
	}
	public function save_ajax_form()
	{
			$userData = $this->session->userdata('userinfo');
			$token = $this->input->post('token'); 
			$errors_array = array();
			$msg = "";$hasEditMode = false;
			if(!in_array(ADD_ASSIGNMENT_PERMISSION, $this->permissionArray))
		    {
			  $jsonArray = array("status"=>"false",'error_string'=>lang('permission_error'),'redirect_url'=>'dashboard');
			  echo json_encode ($jsonArray) ;
			  exit;
		    }
			////////////Process form data//////////////
			if ( $this->input->server('REQUEST_METHOD') === 'POST' && isset($token) && $token == $this->session->userdata('token')){
				  $this->load->library('upload');
				  $assignment_id = $this->input->post('assignment_id');
				  $assignment_info = $this->assignment_model->getAssignmentDetails( $userData['academicyearid'],$assignment_id );
				  if(count( $assignment_info))
				  {
				   $hasEditMode = true;
				  }
				  //////////////Validate input/////////
				   
				  if( !$hasEditMode ){ 
				    $this->form_validation->set_rules('type', lang('assignment_frm_label_type'), 'trim|required|xss_clean|callback__valid_type');
				  	$this->form_validation->set_rules('csb_id', lang('assignment_frm_label_section'), 'trim|required|xss_clean|callback__valid_csb_id');
				  }
				  $this->form_validation->set_rules('subject_id', lang('assignment_frm_label_subject'), 'trim|required|xss_clean|callback__is_valid_subject_id');
				   
				  $this->form_validation->set_rules('title', lang('assignment_frm_label_title'), 'trim|required|xss_clean|max_length['.MAX_ASSIGNMENT_TITLE_LENGTH.']');
				  $this->form_validation->set_rules('description', lang('assignment_frm_label_intro'), 'trim|required|xss_clean');
				  
				  $this->form_validation->set_rules('assignmentdate', lang('assignment_frm_label_date'), 'trim|required|xss_clean|callback__valid_date');
				  
				  $this->form_validation->set_rules('attachment', "", 'trim|xss_clean|callback__is_valid_attachment_type'); 
				  
				  $this->form_validation->set_rules('status', lang('assignment_frm_label_status'), 'trim|required|xss_clean|_valid_statustype');
				  
				  if($this->form_validation->run() ){
						////////////Save Data////////////
						$dbArray = array( 
						  				'sessionid'=>$userData['academicyearid'],
										'cssid' => $this->input->post('subject_id'),
										'fromdate' => date(MYSQL_DATE_FORMAT,strtotime($this->input->post('fromdate'))),
										'todate' => date(MYSQL_DATE_FORMAT,strtotime($this->input->post('todate'))),
										'title' => $this->input->post('title'),
										'description' => $this->input->post('description'),
										'status' => $this->input->post('status'),
										'updatedby'=>$userData['userid']
										);
						//print_r( $dbArray );
						//exit;
						if( $this->input->post('type') ){
							$dbArray['type'] = $this->input->post('type');
						}
						if( $assignment_id > 0  ){//edit
							$this->assignment_model->updateAssignment($assignment_id,$dbArray);
						}else{//add
							$dbArray['createdby'] = $userData['userid'];
						    $assignment_id = $this->assignment_model->addAssignment($dbArray);
							$msg = lang('assignment_added');
						}
					     if( $assignment_id > 0 ){
							/////////////////////Save Attachment////////////////////////////////////////////
							if(!empty($_FILES["attachment"]["name"]) && isset($_FILES["attachment"]["name"])) {
								$config['upload_path']   = ASSIGNMENT_UPLOAD_PATH ;
								$config['allowed_types'] = ASSIGNMENT_ATTACHMENT_FILE_TYPE_ALLOWED;
								$config['file_name'] = 'attachment_'.$assignment_id.'_'.uniqid() ;
								$this->upload->initialize($config);
								if ( $this->upload->do_upload('attachment',TRUE))
								{
									$uploadedArray = $this->upload->data(); 
									$this->assignment_model->updateAssignment($assignment_id,array('attachment'=>ATTACHMENT_UPLOAD_PATH.'/'.$uploadedArray['file_name']));
								}else{
									// print_r($this->upload->display_errors());
									 //exit;
								}
							}
							if( $assignment_id ){
								 $this->session->set_flashdata('flash_message', $msg);
					 		     $this->session->set_flashdata('flash_status', 's');
								 $jsonArray = array("status"=>"true",'redirect_url'=>'assignment');
							}
					  }
					 
					}else{
						foreach( $this->input->post() as $key=>$val){
							if( form_error($key) ){
								  $errors_array[$key] = strip_tags( form_error($key) );
							}
						}
						if( form_error('attachment') ){
							$errors_array['attachment'] = strip_tags( form_error('attachment') );
						}
						$jsonArray = array("status"=>"false",'errors'=>$errors_array);
					}
			}
			else{
				$jsonArray = array("status"=>"false",'error_string'=>lang('invalid_request'));
			}
			echo json_encode ($jsonArray) ;
			exit;
	}
	/** Get Dropdown Via Ajax call****/
	public function ajax_get_subject_dropdown()
	{
		$token = $this->input->post('token'); 
		$errors_array = array();
		////////////Process form data//////////////
		if ( $this->input->server('REQUEST_METHOD') === 'POST' && isset($token) && $token == $this->session->userdata('token')){
			//////////Get Class Branch Section/////
			if( $this->input->post('csb_ids') ){ 
				$dd_class_branch_section = Utility::getCssJunctionDropdown(array('csb_ids'=>$this->input->post('csb_ids')));
				$jsonArray = array("status"=>"true",'dd_list'=>$dd_class_branch_section);
			}else{
				$jsonArray = array("status"=>"false",'error_string'=>lang('invalid_request'));
			}
		}
		else{
			$jsonArray = array("status"=>"false",'error_string'=>lang('invalid_request'));
		}
		echo json_encode ($jsonArray) ;
		exit;
	}
	///////////Custom vAlidation////////////////////
	////Validate Status 
	function _valid_type($type) {
		$validated = TRUE;
		$type = trim($type);
		if (!in_array($type,array_keys(Utility::$assignmentTypeArray))) {
			$this->form_validation->set_message('_valid_type', lang('assignment_invalid_assignment_type_error'));
			$validated =  FALSE;
		}
		return $validated;
	}
	///Validate csb id 
	function _valid_csb_id() {
		$userData = $this->session->userdata('userinfo');
		$csb_id = (int)$this->input->post('csb_id');
		if (!$this->cssjunction_model->isVaidCsbId( $userData['userid'],$userData['academicyearid'],$csb_id)) {
			$this->form_validation->set_message('_valid_csb_id', lang('assignment_invalid_class_section_error'));
			return FALSE;
		}else{
			return TRUE;
		}
	}
	//Validate subject id
	function _is_valid_subject_id() {
		$csb_id = "";
		$assignment_id = $this->input->post('assignment_id');
		$assignment_info = $this->assignment_model->getAssignmentDetails( $this->userData['academicyearid'],$assignment_id );
		if(count( $assignment_info))
		{
		  ///////////Get Selected Values//////////////
		  $dataCssJunction = $this->assignment_model->getSelectedCssJunction( $this->userData['academicyearid'],$assignment_info->cssid);
		  if( $dataCssJunction ){ 
			  $csb_id = $dataCssJunction['selectedCsbids'];
		  }
		}else{
			$csb_id = (int)$this->input->post('csb_id');
		}  
		$subject_id = (int)$this->input->post('subject_id');
		
		if (!$this->cssjunction_model->isVaidSubjectId($this->userData['userid'],$this->userData['academicyearid'],$subject_id,$csb_id)) {
			$this->form_validation->set_message('_is_valid_subject_id', lang('assignment_invalid_class_subject_error'));
			return FALSE;
		}else{
			return TRUE;
		}
	} 
	//Validate date 
	function _valid_date() {
		$fromdate = $this->input->post('fromdate');
		$todate = $this->input->post('todate');
		$assignment_id = (int)$this->input->post('assignment_id');
		if( $assignment_id >  0 ){
			 //Need to discuss
			return TRUE;
		}else if( strtotime($fromdate) > strtotime($todate) ){
			$this->form_validation->set_message('_valid_date', lang('assignment_invalid_from_date_error'));
			return FALSE;
		}
		return TRUE;
	} 
	
	/*** Validate The attachment***/
	function _is_valid_attachment_type(){	
		if( !empty( $_FILES['attachment']['name'] )){
			if( is_valid_file_ext($_FILES['attachment'],'assignment_attachment')  == false){
				$this->form_validation->set_message('_is_valid_attachment_type', lang('assignment_allowed_file_type'));
				return FALSE;
			}elseif( is_valid_file_size($_FILES['attachment'],ASSIGNMENT_ATTACHMENT_MAX_FILE_SIZE) == false){
				
				$this->form_validation->set_message('_is_valid_attachment_type', lang('assignment_allowed_max_file_size'));
				return FALSE;
			}
		} 
		return TRUE;
	 } 
	
}
