<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2013      Pierre-Emmanuel DOUET	<tathar.dev@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       dev/skeletons/machine.class.php
 *  \ingroup    mymodule othermodule1 othermodule2
 *  \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *				Initialy built by build_class_from_table on 2013-03-11 20:12
 */

// Put here all includes required by your class file
//require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");

require_once(DOL_DOCUMENT_ROOT."/machine/class/machine_model.class.php");


/**
 *	Put here description of your class
 */
class Machine // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='machine';			//!< Id that identify managed objects
	//var $table_element='machine';	//!< Name of table without prefix where object is stored

    var $id;
    
//	var $fk_model;

	var $trademark;
	var $type_id;
	var $model;
	var $n_model;					
	var $serial_num;
	var $short_ref;
	var $tms='';

    


    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
        return 1;
    }


    /**
     *  Create object into database
     *
     *  @param	User	$user        User that create
     *  @param  int		$notrigger   0=launch triggers after, 1=disable triggers
     *  @return int      		   	 <0 if KO, Id of created object if OK
     */
    function create($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
        
//		if (isset($this->fk_model))   $this->fk_model=trim($this->fk_model);
		if (isset($this->serial_num)) $this->serial_num=trim($this->serial_num);
		if (isset($this->short_ref)) $this->short_ref=trim($this->short_ref);
		if (isset($this->trademark))  $this->trademark=trim($this->trademark);
		if (isset($this->type_id))      $this->type_id=trim($this->type_id);
		if (isset($this->model))      $this->model=trim($this->model);
		if (isset($this->n_model))    $this->n_model=trim($this->n_model);
        

		// Check parameters
		// Put here code to add control on parameters values
//<Tathar>
		dol_syslog(get_class($this)."::create //<Tathar>", LOG_DEBUG);
		$obj_model = new Machine_model($this->db);
		$obj_model->trademark = $this->trademark;
		$obj_model->type_id     = $this->type_id;
		$obj_model->model     = $this->model;
		$obj_model->n_model   = $this->n_model;
		$fk_model = $obj_model->create($user, $notrigger);

		$sql = "SELECT";				//on test si la machine existe deja;
		$sql.= " rowid";
		
        $sql.= " FROM ".MAIN_DB_PREFIX."machine";
        $sql.= ' WHERE fk_model = '.$fk_model.' AND';
		$sql.= ' serial_num = '."'".$this->serial_num."'";

    	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);

        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql)) //le modele existe
            {
                $obj = $this->db->fetch_object($resql);
                $this->id = $obj->rowid; 
				$this->db->free($resql);
	            return $this->id;			//le modele existe, on le retourn        
            }
            
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::create ".$this->error, LOG_ERR);
            return -1;
        }
		dol_syslog(get_class($this)."::create //</Tathar> fk_model = ".$fk_model, LOG_DEBUG);
//</Tathar>									//le modele n'existe pas
        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."machine(";
		
		$sql.= "fk_model, ";
		$sql.= "serial_num, ";
		$sql.= "short_ref";
        $sql.= ") VALUES (";
		$sql.= " "."'".$fk_model."',";
		$sql.= " "."'".$this->db->escape($this->serial_num)."',";
		$sql.= " "."'".$this->db->escape($this->short_ref)."'";
		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."machine");

			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
			}
        }

        // Commit or rollback
        if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
            return $this->id;
		}
    }


    /**
     *  Load object in memory from database
     *
     *  @param	int		$id    Id object
     *  @return int          	<0 if KO, >0 if OK
     */
    function fetch($id)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " m.rowid,";
		$sql.= " m.serial_num,";
		$sql.= " m.short_ref,";
		$sql.= " m.tms,";

		$sql.= " mm.type_id,";
		$sql.= " mm.model,";
		$sql.= " mm.n_model,";

		$sql.= " mt.trademark";
		
        $sql.= " FROM ".MAIN_DB_PREFIX."machine as m";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."machine_model as mm ON m.fk_model = mm.rowid";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."machine_trademark as mt ON mm.fk_trademark = mt.rowid";
        $sql.= " WHERE m.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id    	  = $obj->rowid;
				$this->serial_num = $obj->serial_num;
				$this->short_ref = $obj->short_ref;
				$this->tms 		  = $this->db->jdate($obj->tms);
				$this->type_id 	  = $obj->type_id;
				$this->model 	  = $obj->model;
				$this->n_model 	  = $obj->n_model;
				$this->trademark  = $obj->trademark;

                
            }
            $this->db->free($resql);

            return 1;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *  Update object into database
     *
     *  @param	User	$user        User that modify
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
     *  @return int     		   	 <0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
        
		if (isset($this->serial_num)) $this->serial_num = trim($this->serial_num);
		if (isset($this->short_ref)) $this->short_ref = trim($this->short_ref);
		if (isset($this->trademark))  $this->trademark  = trim($this->trademark);
		if (isset($this->type_id))      $this->type_id		= trim($this->type_id);
		if (isset($this->model))      $this->model		= trim($this->model);
		if (isset($this->n_model))    $this->n_model	= trim($this->n_model);

        

		// Check parameters
		// Put here code to add control on parameters values
//<Tathar>
		$obj_model = new Machine_model($this->db);
		$obj_model->trademark = $this->trademark;
		$obj_model->type_id 	  = $this->type_id;
		$obj_model->model 	  = $this->model;
		$obj_model->n_model   = $this->n_model;
		
		$fk_model = $obj_model->create($user);
//</Tathar>

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."machine SET";
        
		$sql.= " fk_model=".(isset($this->fk_model)?$this->fk_model:"null").",";
		$sql.= " serial_num=".(isset($this->serial_num)?"'".$this->db->escape($this->serial_num)."'":"null").",";
		$sql.= " short_ref=".(isset($this->short_ref)?"'".$this->db->escape($this->short_ref)."'":"null").",";
		$sql.= " tms=".(dol_strlen($this->tms)!=0 ? "'".$this->db->idate($this->tms)."'" : 'null')."";

        
        $sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
	    	}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			$obj_model->clean($user, $notrigger);
			return 1;
		}
    }


 	/**
	 *  Delete object in database
	 *
     *	@param  User	$user        User that delete
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
	 *  @return	int					 <0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$this->db->begin();

		if (! $error)
		{
			if (! $notrigger)
			{
				// Uncomment this and change MYOBJECT to your own tag if you
		        // want this action call a trigger.

		        //// Call triggers
		        //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		        //$interface=new Interfaces($this->db);
		        //$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
		        //if ($result < 0) { $error++; $this->errors=$interface->errors; }
		        //// End call triggers
			}
		}

		if (! $error)
		{
    		$sql = "DELETE FROM ".MAIN_DB_PREFIX."machine";
    		$sql.= " WHERE rowid=".$this->id;

    		dol_syslog(get_class($this)."::delete sql=".$sql);
    		$resql = $this->db->query($sql);
        	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror();}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			$obj_model = new Machine_model($this->db);
			$obj_model->clean($user, $notrigger);
			return 1;
		}
	}



	/**
	 *	Load an object from its id and create a new one in database
	 *
	 *	@param	int		$fromid     Id of object to clone
	 * 	@return	int					New id of clone
	 */
/*	function createFromClone($fromid)
	{
		global $user,$langs;

		$error=0;

		$object=new Machine($this->db);

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		$object->id=0;
		$object->statut=0;

		// Clear fields
		// ...

		// Create clone
		$result=$object->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$object->error;
			$error++;
		}

		if (! $error)
		{


		}

		// End
		if (! $error)
		{
			$this->db->commit();
			return $object->id;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}
*/

	/**
	 *	Initialise object with example values
	 *	Id must be 0 if object instance is a specimen
	 *
	 *	@return	void
	 */
	function initAsSpecimen()
	{
		$this->id=0;
		
		$this->trademark='Brother';
		$this->type_id='FAX';
		$this->model='FAX 8360P';
		$this->n_model='N/A';
		$this->serial_num='1234567890';
		$this->short_ref='890';
		$this->tms='';

		
	}

	/**
	 *	Initialise object with example values
	 *	Id must be 0 if object instance is a specimen
	 *
	 *	@return	void
	 */
	function setType_id( $id,$type_id )
	{		
		       // Update request
        $sql = "SELECT";
		$sql.= " mm.rowid as rowid";
        $sql.= " FROM ".MAIN_DB_PREFIX."machine as m";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."machine_model as mm ON m.fk_model = mm.rowid";
        $sql.= " WHERE m.rowid = ".$id;

    	dol_syslog(get_class($this)."::setType sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if ($resql)
		{
			$return = 0;
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

				$objmm = new Machine_model($this->db);
				$return =  $objmm->setType_id($obj->rowid, $type_id);
				if ($return == 1 ) $this->type_id= $type_id;
            }
            $this->db->free($resql);

            return $return;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
		
	}

	/**
	 *	Initialise object with example values
	 *	Id must be 0 if object instance is a specimen
	 *
	 *	@return	void
	 */
	function setSerialNum( $id, $serial_num )
	{
		
		       // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."machine SET";
        
		$sql.= ' serial_num='."'".$serial_num."'";

        $sql.= " WHERE rowid=".$id;

		$this->db->begin();

		dol_syslog(get_class($this)."::setSerialNum sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror();}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::setSerialNum ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}

		
	}

	/**
	 *	Initialise object with example values
	 *	Id must be 0 if object instance is a specimen
	 *
	 *	@return	void
	 */
	function setShortRef( $id, $short_ref )
	{
		
		       // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."machine SET";
        
		$sql.= ' short_ref='."'".$short_ref."'";

        $sql.= " WHERE rowid=".$id;

		$this->db->begin();

		dol_syslog(get_class($this)."::setShortRef sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror();}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::setShortRef ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}

		
	}

	function clean($user, $notrigger=0)
	{
		$sql = "SELECT m.rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."machine AS m";
		$sql.= " WHERE m.rowid";
		$sql.= " NOT IN (";
    	$sql.= " SELECT r.fk_machine";
		$sql.= " FROM ".MAIN_DB_PREFIX."repair AS r";
//		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."machine AS m ON r.fk_machine = m.rowid";
 		$sql.= " )";

//		$this->db->begin();
		dol_syslog(get_class($this)."::clean sql=".$sql, LOG_DEBUG);
		$result=$this->db->query($sql);
		if ( $result)
        { 
			$num = $this->db->num_rows($result);
			$id = $this->id;
			dol_syslog(get_class($this)."::clean num_rows = $num" , LOG_DEBUG);
			while ($obj=$this->db->fetch_object($resql))
            {
				$this->id = $obj->rowid;
				dol_syslog(get_class($this)."::clean delete row = $this->id" , LOG_DEBUG);
				$this->delete( $user, $notrigger);
dol_syslog(get_class($this)."::clean delete row = $this->id" , LOG_DEBUG);
            }

//			$objmod = new Machine_model($this->db);
//			dol_syslog(get_class($this)."::clean", LOG_DEBUG);
//			$objmod->cleanModel($user, $notrigger);
			return $num;
		}
		else
        {
            dol_print_error($this->db);
            $this->error=$this->db->error();
            return -1;
        }

	}

}
?>
