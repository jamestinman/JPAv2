<?php
// Society's simplified database connection class 
require_once(dirname(__FILE__).'/FormController.php');

class Form extends Society\FormController {

  function __construct($table=false,$defOrName=false,$optionsOrIsSubForm=false) {
    global $DB;
    parent::__construct($DB, $table, $defOrName, $optionsOrIsSubForm);
  }
}
?>
