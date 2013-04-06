<?php
/* Copyright (C) 2013      Pierre-Emmanuel DOUET	<tathar.dev@gmail.com>
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
 *	\file       htdocs/core/class/html.formcompany.class.php
 *  \ingroup    core
 *	\brief      File of class to build HTML component for third parties management
 */


/**
 *	Class to build HTML component for third parties management
 *	Only common components are here.
 */
class FormMachine
{
	var $db;
	var $error;



	/**
	 *	Constructor
	 *
	 *	@param	DoliDB	$db		Database handler
	 */
	function FormMachine($db)
	{
		$this->db = $db;

		return 1;
	}

	/**
	 *  Return combo list with machine type
	 *
	 *  @param  string	$selected   type preselected
	 * 	@param	string	$htmlname	Name of HTML select combo field
	 *  @return	void
	 */
	function select_machine_type($selected='',$htmlname='machine_type_id')
	{
		global $conf,$langs,$user;
		$langs->load("repairlang@repair");

		$out='';

		$sql = "SELECT rowid, code, label, active FROM ".MAIN_DB_PREFIX."c_machine_type";
		$sql.= " WHERE active = 1";

		dol_syslog("FormRepair::select_c_machine_type sql=".$sql);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$out.= '<select class="flat" name="'.$htmlname.'">';
			$out.= '<option value="">&nbsp;</option>';
			$num = $this->db->num_rows($resql);
			$i = 0;
			if ($num)
			{
				while ($i < $num)
				{
					$obj = $this->db->fetch_object($resql);
					if ($selected == $obj->code)
					{
						$out.= '<option value="'.$obj->code.'" selected="selected">';
					}
					else
					{
						$out.= '<option value="'.$obj->code.'">';
					}
					// Si traduction existe, on l'utilise, sinon on prend le libelle par defaut
					$out.= ($langs->trans("MACHINE_TYPE_".$obj->code)!="MACHINE_TYPE_".$obj->code ? $langs->trans("MACHINE_TYPE_".$obj->code) : ($obj->label!='-'?$obj->label:''));
//					$out.= ($obj->label!='-'?$obj->label:'');
					$out.= '</option>';
					$i++;
				}
			}
			$out.= '</select>';
//			if ($user->admin) $out.= info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionnarySetup"),1);
		}
		else
		{
			dol_print_error($this->db);
		}

		return $out;
	}

	/**
	 *    Return label of a type_id code
	 *
	 *    @return     string      Translated name of a Support code
	 */
	function get_machine_type( $type_id )
	{
//		global $langs;
//		$langs->load("dict");

//		$code=$this->support_id;
//        return $langs->trans("Support".$code)!="Support".$code ? $langs->trans("Support".$code) : '';

		$sql = 'SELECT';
        $sql.= ' label';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'c_machine_type';
        $sql.= ' WHERE code = '."'".$type_id."'";
		dol_syslog(get_class($this)."::get_machine_type sql=".$sql);
        $result=$this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);
                $label = $obj->label;
            }
            $this->db->free($result);
			return $label;
		}
	}

}

?>
