<?php
/*
Class Tc_createtable operates with createtable-tag from install.xml.
Made by Imrich Scindler
Released under GPL
*/

class Tc_addlangdef extends ContribInstallerBaseTag {
    var $tag_name='addlangdef';
    var $encod; //Array with lang encodings.
    // Class Constructor
    function Tc_addlangdef($contrib='', $id='', $xml_data='', $dep='') {
        $this->params=array(
            'lng'=>array(
                                'sql_type'=>'varchar (255)',
                                //b 2.0.4:
                                //'xml_error'=>"no language definition; "
                                // if we use default language (english) - this is no error if user do not set this parameter
                                //e 2.0.4
                                ),
            'dir'=>array(
                                'sql_type'=>"ENUM ('admin', 'catalog')",
                                //'xml_error'=>"no table definition; " default - normal
                                ),
            'name'=>array(
                                'sql_type'=>'varchar (255)',
                                'xml_error'=>"no text const definition; "
                                ),

        );
        $this->ContribInstallerBaseTag($contrib, $id, $xml_data, $dep);
    }

    function get_data_from_xml_parser($xml_data='') {
    	$this->data['lng'] = array();
    	$this->data['dir'] = array();
    	$this->data['name'] = array();
    	$this->data['value'] = array();
    	$tags = $xml_data->getElementsByTagName('lang');
    	for($i=0 ;$i < $tags->getLength(); $i++){
            $this->data['lng'][$i] =(($this->getITagAttr($tags,$i,'lng')) ? $this->getITagAttr($tags,$i,'lng') : 'russian');
            $this->data['dir'][$i]   		=$this->getITagAttr($tags,$i,'type');

            $this->encod[$i] = $this->get_lang_file_encoding( (($this->data['dir'][$i]=='admin') ? 'lang/' . $this->data['lng'][$i] . '/admin/' : 'lang/' . $this->data['lng'][$i] . '/'). $this->data['lng'][$i].".php");
        	$this->data['name'][]   	=$this->getITagAttr($tags,$i,'name');
        	$this->data['value'][]  	=$this->getITagText($tags,$i);
    	}
    }
    //===============================================================

    function lang_define($name, $value, $encod) {return "  define('".strtoupper($name)."','".unicode2win($value, $encod)."');\n";}
    function add_def($tblrowsa=array(), $dir='catalog', $action='remove') {
        if (count($tblrowsa)>0) {
            foreach ($tblrowsa as $lang=>$text) {
                $code=$this->linebreak_fixing("\n" . $this->comment_string($text));
                if ($this->isEscom()) {
                    $filename= ($dir=='admin'? 'lang/' . $lang . '/admin/' : 'lang/' . $lang . '/').$lang.".php";
                } else {
                    $filename= ($dir=='admin'? 'lang/' . $lang . '/admin/' : 'lang/' . $lang . '/').$lang.".php";
                }

                if ($action=='install')     $this->add_file_end($filename, $code);
                elseif ($action=='remove')    $this->remove_file_part($filename, $code);
            }
        }
    }
    function do_remove() {return $this->do_install('remove');}
    function do_install($action='install') {
    global $message;
        $tblrowsa=array();
        $tblrows=array();
        for($i=0; $i<count($this->data['value']); $i++){
            if ($this->data['dir'][$i]=='admin') {
                $tblrowsa[$this->data['lng'][$i]].=$this->lang_define($this->data['name'][$i], $this->data['value'][$i], $this->encod[$i]);
            } else {
                $tblrows[$this->data['lng'][$i]].=$this->lang_define($this->data['name'][$i], $this->data['value'][$i], $this->encod[$i]);
            }
        }
        $this->add_def($tblrows, 'catalog', $action);
        $this->add_def($tblrowsa, 'admin', $action);
        return $this->error;
    }

}
?>