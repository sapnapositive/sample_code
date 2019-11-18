<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Assignment_model extends MY_Model {
	public function __construct()
	{
			// Call the CI_Model constructor
			parent::__construct();
			$this->userData = $this->session->userdata('userinfo');
	}
	//Add new assignment
	function addAssignment($data) {
		$data['createddate']  = date('Y-m-d H:i:s');
        $this->db->insert(Utility::$assignment_table, $data);
        $insert_id = $this->db->insert_id();
        return $insert_id;
    }
	
	/*** Update the assignment ***/
	function updateAssignment($assignment_id,$data) {
		$data['lastmodifieddate'] = date('Y-m-d H:i:s');
        $this->db->where("id", (int)$assignment_id);
        $this->db->update(Utility::$assignment_table, $data);
        return $this->db->affected_rows();
    }
	public function getAssignmentList($sessionid,$searchArray = array(), $limit="", $offset="", $orderBy="id", $order="DESC")
	{
			//Note: todate_f and fromdate_f = Formatted Date(s)
			$this->db->select("a.id,a.type,a.sessionid,cssid,fromdate,todate,s.name as subjectname,concat(b.name,' ',c.name,' ',s1.name) as class_branch_section_name, title,description,a.status,attachment,DATE_FORMAT(a.`fromdate`,'%d %b %Y') AS fromdate_f,DATE_FORMAT(a.`todate`,'%d %b %Y') AS todate_f",false); 
			$this->db->from( Utility::$assignment_table .' AS a');
			$this->db->join( Utility::$cssjunction_table.' AS css','css.id = a.cssid');
			$this->db->join( Utility::$subject_table.' AS s','css.subjectid = s.id');
			$this->db->join( Utility::$csbjunction_table.' AS csb','csb.id = css.csbid');//join with class section branch table				
			$this->db->join( Utility::$class_table .' As c','c.id = csb.classid' );//join with section table 
			$this->db->join( Utility::$section_table .' As s1','s1.id = csb.sectionid' );//join with section table
			$this->db->join( Utility::$branch_table .' As b','b.id = csb.branchid' );//join with branch table
				
			/////Add Restriction////
			if( $this->userData['access'] == RECORD_LEVEL_ACCESS_CLASS ){
				$this->db->where_in('css.csbid', "select csbid from ". Utility::$cssjunction_table." where staffid =". (int)$this->userData['userid'] , false);
			}
			if(count($searchArray) && $searchArray['q']){ 
			$this->db->group_start();
				$this->db->like(" a.title",$searchArray['q']);
				$this->db->or_like(" a.type",$searchArray['q']);
				$this->db->or_like(" a.fromdate",$searchArray['q']);
				$this->db->or_like(" a.todate",$searchArray['q']);
				$this->db->or_like(" a.description",$searchArray['q']);
				$this->db->or_like(" a.status",$searchArray['q']);
			 $this->db->group_end();
			}
			$this->db->where(" a.sessionid ",(int)$sessionid);
			$this->db->order_by($orderBy,$order);
			$this->db->group_by("a.id");
			/////////////////GET Total Records/////////////////
			$tempdb = clone $this->db;
			 $sql= $tempdb->get_compiled_select();
			// exit;
			$total_records_query = $tempdb->query( $sql);
			$total_records = $total_records_query->num_rows();
			////////////////ADD Limit///////////////
			if( $limit){
				if($offset)
				{
					$this->db->limit((int)$limit,(int)$offset);
				}
				else
				{
					$this->db->limit((int)$limit,0);
				}
			}
			$list['records'] = $this->db->get()->result();
			$list['total_records'] = $total_records; 
			return $list;
	}
	public function getAssignmentDetails($sessionid,$assignment_id)
	{
			$this->db->select("a.id,a.type,a.sessionid,cssid,fromdate,todate,title,description,status,attachment,DATE_FORMAT(a.`fromdate`,'%d %b %Y') AS fromdate_f,DATE_FORMAT(a.`todate`,'%d %b %Y') AS todate_f",false);
			$this->db->from( Utility::$assignment_table .' AS a');
			$this->db->where("a.sessionid ",(int)$sessionid);
			$this->db->where('a.id',(int)$assignment_id); 
			$query = $this->db->get();
			return ( $query->num_rows() > 0 ) ? $query->row(): NULL;
	}
	/** Get Selected Subject,branch ***/
	public function getSelectedCssJunction($sessionid,$cssid)
	{
			if($cssid > 0 && $sessionid > 0){
				$returnArray = array();
				$this->db->select("group_concat(css.subjectid) AS selectedSubjectsIds,group_concat(css.csbid) AS selectedCsbids");
				$this->db->from( Utility::$cssjunction_table.' AS css');
				$this->db->where('css.id',(int)$cssid); 
				$query = $this->db->get();
				if($query->num_rows() >0 ){
					$returnArray['selectedSubjectsIds'] = explode(',',$query->row()->selectedSubjectsIds);
					$returnArray['selectedCsbids'] = explode(',',$query->row()->selectedCsbids);
				}
				return $returnArray;
			}
	}
}