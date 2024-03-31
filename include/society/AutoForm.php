<?php
namespace Society;

// AutoForm
/*
AutoForm automatically creates HTML lists and submittable forms using database table definitions.
It's purpose is to quickly construct a simple editable database front end with as little as 2 lines of code, e.g.:
    $form = new AutoForm($DB, 'products');
		echo $form->get();

1. Construct a form for a particular database record:
  $form = new AutoForm($DB, 'products', $options); // options is a configuration array e.g. ['keyVal'=>123]
    // OPTIONS
    keyVal   => 123 // Optional keyVal. Creates new if skipped or 0
    ignore   => "modelType"
    readOnly => "pageName,pageType"
    def => [fieldName => $defOptions] // Configure specific fields (see below)

2. Configure specific fields:
  $form = new AutoForm($DB, 'products', ['def'=>$defOptions]); // defOptions is a configuration array for fields
    // DEF OPTIONS
    coltype => "select" // can be int, number, select, selecttext, textarea, checkbox, radio, image
    options => "one,two,three" // options list for radio, checkbox or select. Can be a comma separated list, two-field array or even a SQL SELECT
    title => "Enter name:" // Bespoke title (otherwise title is derived from column name. NB: COMMENTS added to the DDL override this e.g. productType VARCHAR(25) COMMENT 'Type of product')

3. Forcibly overwrite a column's value
  $form->setVal('areaID',4);
    
4. Multi-record list table, with optional edit and new buttons
		$form = new AutoForm($DB, 'pages');
		echo $form->getList();

5. Use more than one table
		$form->addSubForm('products'); // assumes pages has a productID or products has a pageID

6. Multi-record edit table (fully editable grid)
		echo $form->getMultiForm("WHERE brandID=2");

 */
use Exception;

class AutoForm {

  var $type='Form';
  var $options=[];
  var $jsSubmitCode=false;
  var $multiLingual=false;
	var $defaultLanguage="ENG";
  var $items=[]; // Form's render list
  var $cols=[]; // Form's column list
  var $subForms=[]; // Form's sub-form list
  var $joinCols=[]; // Form's joinCol list
  var $sections=[]; var $sectionLookup=[]; var $defaultSectionID="default";
  var $data=false;
  var $includeColourPickerJS=false;
  var $titleCol=null;
  var $hasOrdering=false;
  var $where=null;
  var $orderBy=false;
  var $suppressSubmit=false;
  var $emailFormID=0;
  var $wrapInTable=true;
  var $prefix="";
  var $addAction=false;
  var $isPopUp=false;
  var $display=true;
  var $isSubForm=false;
  var $ownerKeyVal=false; // The keyVal of the owner form. Only valid for subForms
  var $layout='V'; // 'V'ertical or 'H'orizontal (for multi-forms)
  var $isMultiForm=false;
  var $isList=false;
  var $joinType=false; // Only relevant for subForms
  var $name="socform";
  var $table=false;
  var $keyCol=false;
  var $keyVal=false;
  var $floatOver=false; // adds a HTML "title"
  var $title="";
  var $charLimit=false;
  var $editPage=false;
  var $autoComplete="off";
	var $siteRootFromChoc="../"; // Use '../' if admin files are in /chocolate/ (the correct way) or './' if admin files are in the web-root (the old way)
  var $ignorePosted=false;
  var $deleteConfirm="Are you sure?";
  var $maxLineLimit=9999999;
  var $requiredFields=false; // Will not save if these fields are empty e.g. "firstname,surname"
  var $footerJS=false;
  var $limit=500; // Limit for getList() etc
  var $numCols = 0;
  var $pagination=0; // set to num rows per page
  var $submitBtns=false;
  var $showEditButtons=true;
  var $showPrimaryKey = false;
  var $colTypeDefaultSizes = [
    'file' => 30,
    'colour' => 8,
    'color' => 8,
    'price' => 5,
    'currency' => 5,
    'datetime' => 10,
    'date' => 10,
    'float' => 5
  ];
  var $htmlBlocks = [];
  var $rowStyle = false;
  var $permanentRowStyle = false;
  var $saved = false;
  var $deleted = false;

  // Constructor: tell this form which table to use and pull back cols from data-dictionary. DOES NOT set any column vals (only sets defaults)
  function __construct($DB, $table=false, $options=false) {
    $this->DB=$DB;
    // Absorb options    
    $this->options=$options;
    $this->name = getIfSet($options, 'name', $table."Form");
    $this->isSubForm=getIfSet($options,'isSubForm',false);
		$this->submitTo = getIfSet($options, 'submitTo', getPageURL());
		$this->redirPage=(p("redirPage")!="")?p("redirPage"):((isset($_SERVER["HTTP_REFERER"]))?$_SERVER["HTTP_REFERER"]:"");
    // Set up the Col objects from database
    $this->useTable($table);
    // Apply definitions
		if (isset($options['def'])) {
      $this->def($options['def']);
    }
    // Apply options
    if (isset($options['uploadDir'])) $this->setUploadDir($options['uploadDir']);
    if (isset($options['keyVal'])) $this->setKeyVal($options['keyVal']);
    if (isset($options['orderBy'])) $this->orderBy = $options['orderBy'];
    if (isset($options['limit'])) $this->limit = $options['limit'];
    if (isset($options['where'])) $this->where = $options['where'];    
    if (isset($options['showEditButtons'])) $this->showEditButtons = $options['showEditButtons'];    
    if (isset($options['hidden'])) $this->doNotDisplay($options['hidden']);  
    if (isset($options['editPage'])) $this->editPage = $options['editPage'];  
    if (isset($options['showPrimaryKey'])) $this->showPrimaryKey = $options['showPrimaryKey'];  
    if (isset($options['order'])) $this->reorder($options['order']);
    if ($this->showPrimaryKey) {
      $this->disable($this->keyCol);
    } else {
      $this->ignore($this->keyCol);
    }
  }

  function reorder($order) {
    $tmpSectionItems = [];
    foreach (explode(',', $order) as $colName) {
      if ($col=$this->getItem($colName)) {
        $colName = $col->getFullName();
        // Find this col's section
        $sectionID = $col->getSection();
        $i = $this->sectionLookup[$col->sectionID];
        // Remove it from it's current location
        if (isset($this->sections[$i]['items'][$colName])) {
          unset($this->sections[$i]['items'][$colName]);
        }
        // Remember it
        if (!isset($tmpSectionItems[$i])) $tmpSectionItems[$i]=[];
        $tmpSectionItems[$i][$colName] = true;
      }
    }
    // Move over all remaining items
    foreach ($tmpSectionItems as $i=>$items) {
      foreach ($this->sections[$i]['items'] as $colName=>$true) {
        unset($this->sections[$i]['items'][$colName]);
        $tmpSectionItems[$i][$colName] = $true;
      }
    }
    // Finally copy everything back
    foreach ($tmpSectionItems as $i=>$items) {
      foreach ($items as $colName=>$true) {
        $this->sections[$i]['items'][$colName] = $true;
      }
    }
  }

  function setName($newName) {
    $this->name=$newName;
    $this->formName=$newName;
    for ($s=0; $s<sizeOf($this->subForms); $s++) { $this->subForms[$s]->setName($newName); }
  }

	// Backwards compatibility functions
	/*
	function getTitleFromName($colName) { return unCamel($colName); }
	*/
	function unCamel($s) { return unCamel($s); }

	// Picks up the table definition from the data dictionary and creates a "Col" object for each column
  function useTable($table) {
    $this->table=$table;
    if ($ddCols=$this->DB->getColsFromDD($this->table)) {
      foreach ($ddCols as $r1) { $this->addColFromDD($r1); }
    } else {
      echo "Table '".$this->table."' not found in DD - no Cols added to ".$this->name;
    }
  }

	// Get a form using a stringSource. Deals with the errors as well. Will return a form, a form with errors highlighted or returns false. Format of params follows the Form format e.g. title|Title:|select|none|Mr&&Mrs~..
  function useStringDef($stringDef) {
    $c="";
		$fields = [];
		foreach(explode("~",$stringDef) as $paramSet) {
			$params = explode('|', $paramSet);
			if (isset($params[0])) {
			  $colName=$params[0];
    	  $title=(isset($params[1]))?$params[1]:null;
    	  $coltype=(isset($params[2]))?$params[2]:'text';
    	  $require=(isset($params[3]) && $params[3]=='require')?true:false;
  		  $ops=(isset($params[4]) && strpos(strToUpper($params[4]),"DELETE")===false)?explode('&&',$params[4]):null;
  		  if(is_array($ops) && sizeof($ops > 1)) $ops = implode(',', $ops);
        if(is_array($ops) && sizeof($ops == 1)) $ops = $params[4];
			  $this->addCol($colName,$title,$coltype,$ops,null,null,null,$require);
      }
		}
	}

  // Called from useTable() for each column found in the data dictionary
  // Converts COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH etc into AutoForm options
  function addColFromDD($r1) {
    if (!isset($r1['COLUMN_NAME'])) {
      die("COLUMN_NAME missing from:".sTest($r1));
    }
    // Remember the primary key but ignore the field
    $isPrimaryKey = (isset($r1['COLUMN_KEY']) && $r1['COLUMN_KEY']=='PRI')?1:0;
    if ($isPrimaryKey) {
      $this->keyCol=$r1['COLUMN_NAME'];
    }
    $cc=getIfSet($r1,'COLUMN_COMMENT');
    if ($cc) {
      if(substr($cc, 0, 1) != "~"){
        $colTitle=$cc;
      }else{
        $colValidation = $cc;
        if(strstr($colValidation, "require")){
          $colValidation = str_replace("~", "", $colValidation);
          $colValidation = str_replace("require", "", $colValidation);
          $colRequired = true;
        }else{
          $colRequired = false;
        }
      }
    }
    if(!isset($colName)) $colName=$r1['COLUMN_NAME'];
    if(!isset($colTitle)) $colTitle=unCamel($colName);
    if(!isset($colValidation)) $colValidation = "none";
    if(!isset($colRequired)) $colRequired = false;
    $maxChars=(isset($r1['CHARACTER_MAXIMUM_LENGTH']))?$r1['CHARACTER_MAXIMUM_LENGTH']:null;
    $default=$r1['COLUMN_DEFAULT'];
    $colOptions=false;
    $formatAsString=(strpos($r1['COLUMN_TYPE'],'char')!==false || strpos($r1['COLUMN_TYPE'],'date')!==false || strpos($r1['COLUMN_TYPE'],'enum')!==false || strpos($r1['COLUMN_TYPE'],'text')!==false)?true:false;
    if (strToLower($r1['COLUMN_NAME'])=='priceinpence') {
      $coltype='priceinprence';
      $colTitle='Price';
    } else if (strToLower($r1['COLUMN_NAME'])=='price') {
      $coltype='currency';
      $colTitle='Price:';
    } else if ($r1['COLUMN_NAME']=='filename') {
      $coltype='file';
      $colTitle='Choose a file';
    } else if ($r1['COLUMN_NAME']=='email') {
      $coltype='email';
      $colTitle='Email';
    } else if (strpos(strToLower($r1['COLUMN_NAME']),'image')!==false) {
      $coltype='image';
      $colTitle='Choose an image';
    } else if ($r1['COLUMN_NAME']=='ordering') {
      $coltype="ordering";
      // $default=getMaxOrdering($this->table);
    } else if ($r1['COLUMN_NAME']=='password') {
      $coltype="password";
    } else if (strpos(strToLower($r1['COLUMN_NAME']),'colour')!==false && strpos(strToLower($r1['COLUMN_NAME']),'id')===false) {
      $coltype="color";
    } else if (strpos(strToLower($r1['COLUMN_NAME']),'color')!==false) {
      $coltype="color";
    } else if ($r1['COLUMN_TYPE']=='varchar(1)' || $r1['COLUMN_TYPE']=='char(1)' || $r1['COLUMN_TYPE']=='int(1)' || $r1['COLUMN_TYPE']=='char(1)') {
      $coltype='checkbox';
    } else if (strpos($r1['DATA_TYPE'],'int')!==false) {
      $coltype="number";
    } else if (strpos($r1['DATA_TYPE'],'float')!==false) {
      $coltype="float";
    } else if (strpos($r1['DATA_TYPE'],'char')!==false) {
      $coltype="text";
    } else if (strpos($r1['DATA_TYPE'],'text')!==false) {
      $coltype="textarea";
    } else if (strpos($r1['DATA_TYPE'],'enum')!==false) {
      $coltype="selecttext";
      $colOptions=$this->getOptionsFromEnum($r1['COLUMN_TYPE']);
    } else {
      $coltype=$r1['DATA_TYPE'];
    }
    // Pick an appropriate size
    $size = 0;
    if ($r1 && isset($r1['CHARACTER_MAXIMUM_LENGTH']) && $r1['CHARACTER_MAXIMUM_LENGTH']>0) {
      $size = min($r1['CHARACTER_MAXIMUM_LENGTH'],36);
    } else if (in($coltype,'number,int,float,decimal')) {
      $size = min($this->getSizeFromColumnType($r1['COLUMN_TYPE']), 8);
    } else if (isset($this->colTypeDefaultSizes[$coltype])) {
      $size=$this->colTypeDefaultSizes[$coltype];
    } else {
    }
    $this->add($r1['COLUMN_NAME'],[
      'rawCol'=>$r1,
      'title'=>$colTitle,
      'coltype'=>$coltype,
      'formatAsString'=>$formatAsString,
      'options'=>$colOptions,
      'default'=>$default,
      'require'=>$colRequired,
      'validation'=>$colValidation,
      'size' => $size
    ]);
    $this->getCol($r1['COLUMN_NAME']);
    if ($r1['DATA_TYPE']=='timestamp') $this->ignore($r1['COLUMN_NAME']);
  }

  function toString($format = 'html') {
    $c = "AutoForm ".$this->table."<br />";
    foreach ($this->cols as $col) {
      $c.= $col->name."<br />";
    }
    $c.="SECTIONS<br />";
    foreach ($this->sections as $section) {
      $items = "";
      foreach ($section['items'] as $itemName=>$true) {
        $item=$this->getItem($itemName);
        if ($item->display) {
          $items = addTo($items, $itemName);
        } else {
          $items = addTo($items, "<strike>".$itemName."</strike>");
        }
      }
      $c.= $section['sectionID']." [".$items."]<br />";
    }
    if ($format != 'html') $c = str_replace($s, '<br />', "\n");
    return $c;
  }

  // Add a column
  function add($name,$v=false) {
	  $coltype=getIfSet($v,'coltype',getIfSet($v,'type'));
	  if (!$coltype) $coltype='text';
	  $coltype=strToLower($coltype);
    if ($coltype == 'form') {
      // New sub-form
      $table = getIfSet($v,'table');
      if (!$table) {
        // Determine from column name??
        throw new Exception("No table given in def for subform ".$name);
      }
      $subV = ['name'=>$table."Form", 'def'=>(isset($v['def']))?$v['def']:[]];
	    return $this->addSubForm($table, $subV);
	  } else  if ($sf = $this->getSubForm($name)) {
      // Adding to a sub-form
      $colName=substr($name,strpos($name,'.')+1,strlen($name));
      $sf->add($colName,$v);
      // Add to this master Form's default section
      if (!$this->isSubForm) $this->addToSection($this->defaultSectionID,$name);
	    return true;
	  }
    // Do not allow the primary key to be duplicated or redefined!
    // if (isset($this->keyCol) && $name==$this->keyCol) return false;
    // $this->require=$v['require'];
    // Remember this if it is a title
    if (isset($v['displayOptions'])) {
      if ($this->isListPage()) {
        // Use displayOptions in preference to options when in list view
        $v['options'] = $v['displayOptions'];
      }
    }
    if ($name=='title' && !(isset($this->titleCol))) {
      $this->titleCol='title';
    }
    if ($coltype=='ordering') {
      $this->hasOrdering=true;
    }
    if ($col=$this->getCol($name)) {
      // Already exists, redefine
      return $this->redefine($name,$v);
    }
    // Define a new column
    $col=new Col($this->DB, $this->numCols, $name, $v);
    $this->numCols++;
    $this->passOwnerDetails($col);
    // If this col isn't in the data dictionary, use it's default as it's value
    if (getIfSet($v,'default') && ($this->table && !$this->DB->columnExists($this->table,$name))) {
      $col->setVal($v['default']);
    }
    // Add to Form's list of columns
    array_push($this->cols,$col);
    // Add to Form's render list
    array_push($this->items,$col);
    // Add to master Form's default section
    if (!$this->isSubForm) $this->addToSection($this->defaultSectionID,$name);
    if ($this->multiLingual) $col->defaultLanguage=$this->defaultLanguage;

    // Seems like a bizarre place to do this, but handle AJAX multifile upload / delete requests
    if ($coltype=='multifile' || $coltype=='multiimage') {
      if (p("a")=='jsUpload' && p("col")==$col->getHtmlName()) {
        // This is called via javascript AFTER the 1st form has been delivered, and BEFORE the user clicks save and the form is submitted properly
        // The whole point of this particular Form's existence is to handle this exact API call - so it can die afterwards
        $res=$col->handleJsUpload();
        // Pump out JSON response
        echo htmlspecialchars(json_encode($res), ENT_NOQUOTES);
        // Do not keep processing this Form, it's work (as an AJAX server) is done
        die;
      } else if (p("a")=='jsDelete') {
        $tableInfo=$col->getMultiFileTable();
        $col->setVal($this->DB->GetOne("SELECT ".$tableInfo['col']." FROM ".$tableInfo['table']." WHERE ".$tableInfo['keyCol']."=".np("id")));
        // Delete actual file
        $col->handleFileDelete();
        // Delete DB record
        $this->DB->execute("DELETE FROM ".$tableInfo['table']." WHERE ".$tableInfo['keyCol']."=".np("id"));
        echo htmlspecialchars(json_encode(array('success'=>true,'id'=>p("id"))), ENT_NOQUOTES);
        die;
      }
    }
    return true;
  }

  function redefine($name, $v) {
    $col = $this->getCol($name);
    if (!$col) return e("Cannot redefine col [".$name."] - not found");
    return $col->set($v);
  }

  // Remove int(...) casing, e.g. int(4) becomes simply 4, float(7,6) becomes 7 etc
	function getSizeFromColumnType($colType) {
    if (strpos($colType,',')) $colType = substr($colType,0,strpos($colType,','));
    return cleanNum($colType);
	}

  // Add a hidden field to the form that does not get processed, only passed to the submit page
  function addHotPotato($name,$val) {
    $this->add($name,['title'=>false, 'coltype'=>"hidden"]);
    $this->setVal($name,$val);
    $this->doNotProcess($name);
    return true;
  }

  // Create a sub-form and set it up depending on the joinType given
  // Valid formTypes are:
  //   it's my PARENT (this main table has a link to the subForm's PK)
  //   it's my CHILD (the subForm has a link to this form's PK)
  //   CHILD-EXTEND, PARENT-EXTEND (handles 1:1 relationships between tables, such as where a table is used to 'extend' another creating a single, virtual entry)
  //   SINGLE-CHILD (produces form for a specific single child, that child being the first encountered using cwcOrPk) - note: you must explicitely override subForm columns you wish to have specific values on save for new records! this could be automatic by picking apart for cwc if given
  // Note: passing 'GUESS' or 'EXTEND' as joinType will make Form figure it all out for you
  // Access the subForm columns using dot notation, e.g. child.twitterID
  function addSubForm($table,$options) {
    // Sub-forms are just child Form objects added to the render list
    $name = getIfSet($options, 'name', $table."Form");
    $options['name'] = $name;
    $joinType = getIfSet($options,'joinType','GUESS');
    $cwcOrPk = getIfSet($options, 'cwcOrPk');
    $options['isSubForm'] = true;
    $sf = new AutoForm($this->DB, $table, $options);
    // Copy over prefs (if set yet)
    $sf->prefix=$this->prefix;
    $sf->layout=$this->layout;
    $sf->isMultiForm=$this->isMultiForm;
    $sf->isList=$this->isList;
    // Guess which joinType to use
		if ($joinType=='GUESS') {
		  if ($this->getCol($sf->keyCol)) {
		    $joinType='PARENT';
		  } else if ($sf->getCol($this->keyCol)) {
		    if ($this->keyVal && $this->DB->GetOne("SELECT COUNT(1) FROM ".$table." WHERE ".$this->keyCol."=".$this->keyVal)>1) {
          $joinType='CHILD';
		    } else {
		      $joinType='CHILD-EXTEND';
		    }
		  } else {
		    e("Cannot guess subForm joinType since this table (".$this->table.") has no ".$sf->keyCol." and subForm table (".$sf->table.") has no ".$this->keyCol);
		  }
		} else if ($joinType=='EXTEND') {
      if ($this->getCol($sf->keyCol)) {
        $joinType='PARENT-EXTEND';
      } else if ($sf->getCol($this->keyCol)) {
        $joinType='CHILD-EXTEND';
      } else {
        echo "<h3>Form ERROR: ".$this->table." cannot be extended using ".$table." as neither table contains a primary key for the other</h3>";
      }
    }
    if ($joinType=='CHILD-SINGLE' && $cwcOrPk) {
      if (isNum($cwcOrPk)) {
        $sf->setKeyVal($cwcOrPk);
      } else {
        $sf->setKeyVal($this->DB->GetOne("SELECT ".$sf->keyCol." FROM ".$table." WHERE ".$cwcOrPk));
      }
    }
    if (in($joinType,'PARENT,PARENT-EXTEND')) $this->hide($sf->keyCol); // Hide our foreign key
    if (in($joinType,'CHILD,CHILD-EXTEND')) $sf->hide($this->keyCol); // Hide their foreign key
    $sf->joinType=$joinType;
    // Add subForm to Form's list of columns
    array_push($this->subForms,$sf);
    // Add subForm to Form's render list
    array_push($this->items,$sf);
    // Add cols to master Form's section list
    foreach ($sf->cols as $col) { $this->addColToSection($this->defaultSectionID,$col); }
    return $sf;
  }

  // Creates a set of checkboxes that link this table to joinedTable via joinTable
  // e.g. addJoinCol('productCategories','Product categories:','productCategories','categories','SELECT categoryID,title FROM categories');
  function addJoinCol($colName,$title,$joinTable,$joinedTable,$joinedTableSQL) {
    $jc=new JoinCol($this->DB, $colName,$title,$joinTable,$joinedTable,$joinedTableSQL);
    $this->passOwnerDetails($jc);
    // Add to Form's list of columns
    array_push($this->joinCols,$jc);
    // Add to Form's render list
    array_push($this->items,$jc);
  }

  function getKeyColFullName() {
    return $this->prefix.(($this->isSubForm)?$this->name."_":"").$this->keyCol;
  }

  // Like Quantum physics, merely looking at the keyVal forces it to set it's value
  function getKeyVal($keyVal=false) {
    if ($this->ignorePosted) return 0;
    $this->setKeyVal(($keyVal!==false)?$keyVal:(($this->keyVal!==false)?$this->keyVal:np($this->getKeyColFullName(),0)));
    return $this->keyVal;
  }
  function setKeyVal($newKeyVal=0,$subFormName=false) {
    if ($subFormName) {
      return ($sf=$this->getSubForm($subFormName))?$sf->setKeyVal($newKeyVal):false;
    } else {
      $this->keyVal=$newKeyVal;
      // Pass this keyVal on to my cols, subForms, joinCols etc...
      foreach ($this->items as $item) { $item->ownerKeyCol=$this->keyCol; $item->ownerKeyVal=$this->keyVal; }
    }
    return true;
  }

	function getOptionsFromEnum($colType) {
		$ops=[];
		// Remove enum(...) casing
		$opsStr=substr($colType,strlen("enum("),strlen($colType)-strlen("enum(")-1);
		foreach (explode(",",$opsStr) as $option) {
			// Remove apostrophes from start and end of each option
			$opt=substr($option,1,strlen($option)-2);
			$ops[$opt]=$opt;
		}
		return $ops;
	}

  function getCol($colName) {
    // SubForm columns use dot notation, e.g. products.title
    $colName = trim($colName); // Remove any leading spaces caused by passing in CSV
    if (strpos($colName,'.')>0) {
      $sfName=substr($colName,0,strpos($colName,'.'));
      $colName=substr($colName,strpos($colName,'.')+1,strlen($colName));
      if ($sf=$this->getSubForm($sfName)) return $sf->getCol($colName);
    } else {
      if (!isset($this->cols)) { e("Trying to find ".$colName." in table ".$this->table." but there are no columns!"); return false; }
      foreach ($this->cols as $searchCol) { if ($searchCol->name==$colName) return $searchCol; }
    }
    return false;
  }

  function getItem($colName) {
    if (strpos($colName,'.')>0) {
      $sfName=substr($colName,0,strpos($colName,'.'));
      $colName=substr($colName,strpos($colName,'.')+1,strlen($colName));
      if ($sf=$this->getSubForm($sfName)) return $sf->getItem($colName);
    } else {
  		foreach ($this->items as $searchItem) { if (isset($searchItem->name) && $searchItem->name==$colName) return $searchItem; }
  	}
		return false;
  }

  // Returns the subForm for the given name. If a col is also given it is ignored (e.g. products and products.title return the same 'products' subForm)
  function getSubForm($name) {
    if (strpos($name,'.')>0) {
      $sfName=substr($name,0,strpos($name,'.'));
      $colName=substr($name,strpos($name,'.')+1,strlen($name));
    } else {
      $sfName=$name;
    }
		foreach ($this->subForms as $searchItem) { if (isset($searchItem->name) && $searchItem->name==$sfName) return $searchItem; }
		return false;
  }

  function getDataVal($colName) {
    if ($this->data && isset($this->data[$colName])) return $this->data[$colName];
    return false;
  }

	function getVal($colName,$default=false) {
		$thisCol=$this->getCol($colName);
		// Use already got value if pos
		if ($thisCol && $thisCol->valSet) return $thisCol->getVal();
    // Have a guess at what this column's value will be
		if ($default && $thisCol) $this->setDefault($colName,$default);
    return $this->guessVal($colName);
	}

  // Between defining the Form and loading / saving it, the column values are generally empty
  // However, if someone calls getVal() we ATTEMPT to service them by guessing what the value will be (as far as we know now)
  // It may come from a posted value, the database or the default
  function guessVal($colName) {
    $this->getKeyVal();
		if ($col=$this->getCol($colName)) {
  		if ($col && $col->valSet) return $col->getVal();
	  	$pVal=$col->pickup(); if ($pVal!==false) return $pVal;
  		if (isset($this->keyVal) && $this->keyVal>0) return $this->DB->GetOne("SELECT ".$col->name." FROM ".$this->table." WHERE ".$this->keyCol."=".$this->keyVal);
	  	if ($col->getDefault()!==false) return $col->getDefault();
    }
    return false;
  }

  function suppressSubmit() { $this->suppressSubmit=true; }

  function setTranslatable($colNames) {
		foreach (explode(',',$colNames) as $colName) {
			if ($col=$this->getCol($colName))
				$col->setTranslatable();
		}
	}

	function setAutoComplete($autoComplete=0) {
	  if (!$autoComplete || $autoComplete=="off") {
  	  $this->autoComplete="off";
  	} else {
  	  $this->autoComplete="on";
  	}
  }

  /***********************************************************************************************
  + Column Manipulation functions
  +
  + These functions allow the user to redefine fields that are already active in a Form.
  + Some methods are merely aliases to main functions, this is for legacy reasons.
  + Ignore methods allow you to leave certain fields out of processing, ignoring a column will ensure
  + it is not processed or displayed.
  + Disabling a column will ensure it is not processed but it will be displayed.
  +
  ***********************************************************************************************/

  // Define each of the columns passed in arr, e.g.:
  //   def([
  //     'startDate'=>['title'=>'First date'],
  //     'productID'=>['options'=>'SELECT productID,title FROM products']
  //   ]);
  function def($arr,$other=false) {
    if (!is_array($arr) && is_array($other)) {
      return $this->redefine($arr,$other); // define a single column
    }
    foreach ($arr as $name=>$v) {
      $this->add($name,$v);
    }
  }
	function setCurrency($colName,$currency) { $thisCol=$this->getCol($colName); $thisCol->setCurrency($currency); }

  function getAllColNames() {
    $r=false;
    foreach ($this->cols as $col) { $r.=(($r)?",":"").$col->getFullName(); }
    foreach ($this->subForms as $sf) $r.=(($r)?",":"").$sf->getAllColNames();
    return $r;
  }
  // Mass set readOnly status on multiple columns
	function readOnly($colNames,$process=false) {
		if ($colNames=="*") {
			$colNames=$this->getAllColNames();
			$this->suppressSubmit=true;
		}
    foreach (explode(',',$colNames) as $colName) { if ($col=$this->getCol($colName)) $col->readOnly($process); }
	}

  function highlight($colNames) {
		if ($colNames=="*") {
			$colNames=$this->getAllColNames();
		}
    foreach (explode(',',$colNames) as $colName) {
      if ($col=$this->getCol($colName)) {
        $col->highlight();
      }
    }
  }

	// SECTIONS (all stored at master form level)
  function addSection($sectionID, $title=false) {
    if ($this->isSubForm) { e("WARNING: Form cannot add section ".$sectionID." to a subForm"); return false; }
	  $newSection=['sectionID'=>$sectionID, 'items'=>[], 'style'=>"", 'title'=>(($title!==false)?$title:$sectionID)];
		array_push($this->sections,$newSection);
		$this->sectionLookup[$sectionID]=sizeOf($this->sections)-1;
	}
	function addDefaultSection($sectionID) { $this->defaultSectionID=$sectionID; } // The section which matches item->sectionID==false
  function addToSection($sectionID, $colNames,$title=false) {
    // Create the section if it does not yet exist
    if (!isset($this->sectionLookup[$sectionID])) $this->addSection($sectionID,$title);
		if ($colNames=="*") $colNames=$this->getAllColNames();
    foreach (explode(',',$colNames) as $colName) {
      if ($col=$this->getItem($colName)) {
        $this->addColToSection($sectionID,$col);
      }
    }
  }
  private function addColToSection($sectionID,$col) {
    // Remove the col from it's existing section
    $fullColName=$col->getFullName();
    $i = $this->sectionLookup[$col->sectionID];
    $newSectionID=$this->sectionLookup[$sectionID];
    if (isset($this->sections[$i]['items'][$fullColName])) unset($this->sections[$i]['items'][$fullColName]);
    $col->setSection($sectionID);
    $this->sections[$newSectionID]['items'][$fullColName]=true;
  }
	function ignore($colNames) {
		if ($colNames=="*") $colNames=$this->getAllColNames();
    foreach (explode(',',$colNames) as $colName) { if ($col=$this->getCol($colName)) $col->ignore(); }
	}
	function ignoreAllBar($colNames) {
		foreach ($this->cols as $col) {
			if (!in($col->name,$colNames)) {
				$col->display=0; $col->process=false;
			}
		}
    // Also re-order, as you almost always want to do this after anyway
    $this->reorder($colNames);
	}

	function disable($colNames) {
		if ($colNames=="*") $colNames=$this->getAllColNames();
    foreach (explode(',',$colNames) as $colName) {
      if ($col=$this->getCol($colName)) $col->disable();
    }
	}

	// Display the column (or hidden column) but do not process
	function doNotProcess($colNames) {
		foreach (explode(',',$colNames) as $colName) {
			$col=$this->getCol($colName);
			$col->process=false;
		}
	}

	function show($colNames) { foreach (explode(',',$colNames) as $colName) { $col=$this->getCol($colName); if ($col) $col->show(); } }
	// Turn col into a hidden field. Still produce it in the HTML, still processes it
	function hide($colNames) { foreach (explode(',',$colNames) as $colName) { $col=$this->getCol($colName); if ($col) $col->hide(); } }
	function hideAllBar($colNames) { foreach ($this->cols as $col) { if (!in($col->name,$colNames)) $col->hide(); } }

  // Add+set a new hidden (silent) field (generally not on the table, such as redirpage, so do not process by default)
  // Handy for passing extra info to the submitted page
  function addHidden($name,$val,$doNotProcess=true) {
    $this->add($name,['title'=>$name, 'coltype'=>"hiddentext"]);
    $this->setVal($name,$val);
    if ($doNotProcess) $this->doNotProcess($name);
  }

  // Do not produce column in the HTML but force it to have a value on save
	function doNotDisplay($colName,$val=false,$default=false) {
    if ($colName==$this->keyCol) return true;
    $col=$this->getCol($colName);
    if (!$col) { e("Col [".$colName."] not found"); return false; }
    $col->doNotDisplay($val,$default);
  }

	function required($colNames) { $this->setRequired($colNames); }
	function setRequired($colNames) {
		if ($colNames=="*") $colNames=$this->getAllColNames();
    foreach (explode(',',$colNames) as $colName) {
      if ($col=$this->getCol($colName)) $col->required();
    }
	}
	function checkRequiredFields() {
		foreach ($this->cols as $col) {
		  $val=$col->getForSQL();
		  // e("Check required ".$col->name."=".(($col->require)?"Required":"NotRequired")." and val[".$val."] ".((isnull($val))?"is null":"is not null"));
		  if ($col->require && isnull($val) && $val!==0) return false;
		}
		return true;
	}

	function doNotValidate($colNames) {
		foreach (explode(',',$colNames) as $colName) {
			$col=$this->getCol($colName);
			$col->validate=false;
		}
	}


  /***********************************************************************************************
  + Low Level Column Manipulation functions
  +
  + These functions expose low level functionality and legacy methods for changing column values,
  + clearing data etc.
  ***********************************************************************************************/

	// NOTE: setVal cannot be used to set the primary key (if you must, set keyVal directly)
	// Use alsoSetDefault=true to retain this value if you subsequently call newEntry() or setKeyVal(0)
	function setVal($colName,$newVal,$alsoSetDefault=false) { if ($col=$this->getCol($colName)) $col->setVal($newVal,$alsoSetDefault); }
	function setDefault($colName,$default) { if ($col=$this->getCol($colName)) $col->setDefault($default); }
	function setTitle($colName,$newTitle) { if ($col=$this->getCol($colName)) $col->title=$newTitle; }
	function enableShuffle($colName,$enable=true) {if ($col=$this->getCol($colName)) $col->setEnableShuffle($enable);}
	function clearForm() {
		unset($this->items, $this->cols, $this->subForms);
		$this->data=false;
		$this->subForms = $this->cols = $this->items = [];
		$this->numCols=0;
		$this->table = $this->keyCol = $this->keyVal = $this->submitBtns = null;
	}
	function clear() { $this->clearForm(); }
	function clearDefaults() { foreach ($this->cols as $col) { $col->setDefault(false); } }
	// Clear a value and keep it clear
	function clearVal($colName,$useDefault=true) { if ($col=$this->getCol($colName)) $col->clearVal($useDefault); }
	// Clear all values, but allow them to be re-picked up from GET, POST or DB
	function clearVals($clearPK=false,$useDefault=true,$keepHiddenValues=true) {
	  foreach ($this->cols as $col) {
      if (!$keepHiddenValues || $col->display==2) {
        $col->clearVal($useDefault);
      }
    }
	  foreach ($this->subForms as $sf) {
      $sf->clearVals($clearPK,$useDefault,false);
    }
	  if ($clearPK) $this->keyVal=0;
	  $this->data=false;
	}
	function ignorePostedData($ignore=true) {
	  $this->ignorePosted=$ignore;
	  foreach ($this->subForms as $sf) { $sf->ignorePostedData($ignore); }
	}
	function reset() { $this->clearVals(true); $this->ignorePostedData(); }
	// Pickup value from the post. The prefix option is used by subForms and Multi-forms to differentiate between rows
	function pickupVals() {
	  // Pick up the key
	  foreach ($this->cols as $col) { $col->pickUpVal(); }
	  foreach ($this->subForms as $sf) { $sf->pickUpVals(); }
	}
	// Only use this for cols and joinCols, as subForms have their own formName, table etc. Replaces the old setTable() function
  function passOwnerDetails($obj) {
    $obj->formName=$this->name;
    $obj->isSubForm=$this->isSubForm;
    $obj->table=$this->table;
    $obj->titleCol=$this->titleCol;
    $obj->ownerKeyCol=$this->keyCol;
    $obj->ownerKeyVal=$this->keyVal;
    $obj->ignorePosted=$this->ignorePosted;
    $obj->prefix=$this->prefix;
  }
	// Allows override of the default titleCol (uses 'title' if it exists on the table)
	function setTitleCol($titleCol) {
		$this->titleCol=$titleCol;
		// Only columns need to know of this change
		foreach ($this->cols as $col) { $col->titleCol=$this->titleCol; }
	  foreach ($this->subForms as $sf) { $sf->setTitleCol($titleCol); }
	}
  function setPrefix($prefix) {
    $this->prefix=$prefix;
    // Inform EVERY sub-object (and their sub-objects). On subForms this call is recursive. On Cols (& JoinCols) it's a simple assignment
    foreach ($this->items as $item) { $item->setPrefix($this->prefix); }
  }
  function setLayout($layout='H') {
    $this->layout=$layout;
	  foreach ($this->subForms as $sf) { $sf->setLayout($layout); } // layout changes only affect subForms +
  }
  function setMultiForm($isMultiForm=true) {
    $this->isMultiForm=$isMultiForm;
    $this->setLayout(($isMultiForm)?'H':'V');
  }
  function setList($isList=true) {
    $this->isList=$isList;
    $this->setLayout(($isList)?'H':'V');
  }
  // function setImgUploadDir($dir) { Col::$uploadDir=$dir; } // For images
  function setUploadDir($dir) { Col::$uploadDir=$dir; } //.((substr($dir,strlen($dir)-1,1)!="/")?"/":""); } // For files

  // Add an HTML block after the form table (e.g. to create a right column)
  // but still inside the <form> tags so any additional fields get sent on
  function addHtmlBlock($html) {
    $this->htmlBlocks[] = $html;
  }

  // Add an HTML row
  function addHtmlRow($title=false,$html=false,$name=false) {
    if (!$html) { $html=$title; $title=false; }
    $name=($name)?$name:(($title)?$title:"htmlRow");
    $this->add($name,['coltype'=>'html','html'=>$html,'title'=>$title]);
  }
  // Explicity set a custom submitBtn by calling this function before getForm
  function addSubmit($btns=false) {
    // foreach ($this->cols as $searchCol) { if ($searchCol->coltype=="submit") return true; }
  	if ($btns) $this->submitBtns=addTo($this->submitBtns,$btns,",",true);
  }
  function addSubmits() {
  	if ($this->suppressSubmit) return false;
  	if (!$this->submitBtns) { // Add default btns
  		$this->submitBtns="Save";
  		if (!$this->isPopUp && !$this->jsSubmitCode) $this->submitBtns.=",Cancel";
  		if (isset($this->keyVal) && $this->keyVal>0 && !$this->jsSubmitCode) $this->submitBtns.=",Delete";
  	}
    $this->add('submitBtn',['coltype'=>"submit",'extra'=>$this->submitBtns]);
  }

	// LOAD DATA
	// ---------
	// Load data pulls back the database row refered to by this->table, this->keyCol and this->keyVal
	// Data is automatically pumped into this->cols (If it's already been called, the data is cached.)
	// If any field is posted to the page, by default these changes are picked up (ready for saving)
	function loadData($forceOverwrite=false) {
		if ((!$this->data || $forceOverwrite) && isset($this->keyCol) && $this->keyVal>0) {
			$sql="SELECT *".(($this->floatOver)?",".$this->floatOver." floatOver":"")." FROM ".$this->table." WHERE ".$this->keyCol."=".$this->keyVal;
			if ($this->data=$this->DB->GetRow($sql)) {
				foreach ($this->cols as $col) {
					// if a column value has already been set - by either a posted variable, or a call to setVal() - then leave it alone
					if ((!$col->valSet && !$col->keepClear) || $forceOverwrite) {
            if (isset($this->data[$col->name])) {
              $col->setVal($this->data[$col->name]);
              if ($this->floatOver) $col->floatOver=$this->data['floatOver'];
            } else {
              $col->setVal($col->getDefault());
              if ($this->floatOver) $col->floatOver=$this->data['floatOver'];
            }
          }
				}
			} else {
        if (buttonPressed("delete")) {
  			  e($this->keyCol." ".$this->keyVal." already deleted");
        } else {
  				error($this->keyCol." ".$this->keyVal." (is not valid.)");
        }
			}
		}
	}

	/***********************************************************************************************
  + General form functions
  +
  + These functions deal with the creation of single and multiforms.
  + getList function will get a list of all the rows in a table and display them as a list of data.
  + Edit buttons appear after the field to allow the editing of the fields values.
  + getForm just chucks out a standard form from the cols in a Form object.
  ***********************************************************************************************/

	function run() {
		$this->handleSubmission();
		echo $this->getForm();
	}

	// On submit bundle form items into JSON payload and AJAX to given url, then display the returned HTML in a popup
	function jsSubmitTo($url) {
		$this->jsSubmitCode="serializeAndSubmit(this,\"".$url."\");";
	}

	// On submit pass JSON to given js func e.g. jsSubmitToFunc(PA.timeCal.submitEvent)
	function jsSubmitToFunc($jsFunctionName, $callbackFnName = false) {
		$this->jsSubmitCode=$jsFunctionName."(serialise(this)";
    if ($callbackFnName) $this->jsSubmitCode.= ", ".$callbackFnName;
    $this->jsSubmitCode.= ");";
	}

  function getFormHead() {
  	if ($this->jsSubmitCode) {
	    $out="\n<form name='".$this->name."' id='".$this->name."' class='formController' method='post' action='#'".(($this->autoComplete=='off')?"autocomplete='off'":"")." onSubmit='".$this->jsSubmitCode." return false;'>";
      $out.="\n<div class='mainForm'>";
	    $out.="\n<input type='hidden' name='serialized' id='serialized' value='1' />";
	    $out.="\n<input type='hidden' name='submitBtn' id='submitBtn' value='' />"; // Submit btns are not passed to the onSubmit handler, so model this activity via triggers on the buttons themselves
  	} else {
	    $out="\n<form enctype='multipart/form-data' name='".$this->name."' id='".$this->name."' class='formController' method='post' action='".$this->submitTo."'".(($this->autoComplete=='off')?"autocomplete='off'":"").">";
      $out.="\n<div class='mainForm'>";
	    $out.="\n<input type='hidden' name='serialized' id='serialized' value='0' />";
	  }
		$out.="\n<input type='hidden' name='socTable' value='".$this->table."' />";
    $out.="\n<input type='hidden' name='redirPage' id='redirPage' value='".$this->redirPage."' />";
    if ($this->addAction) $out.="\n<input type='hidden' name='".$this->addAction."' id='".$this->addAction."' value='".$this->action."' />";
    return $out;
  }

  function outputKey() {
    $partOfTable=($this->isMultiForm || $this->isSubForm);
    $pk="<input type='hidden' name='".$this->getKeyColFullName()."' value='".$this->getKeyVal()."' />\n";
    // Output the PK
    if ($partOfTable) { $pk="<td><span class='hidden'>".$pk."</span></td>"; if ($this->layout=='V') $pk="<tr class='hidden'><td>".$pk."</td></tr>"; }
    return $pk;
  }

  function getFormItems() {
    $c="\n";
    // Output items in each section, the default section last
    foreach ($this->sections as $section) {
      if ($section['sectionID']!='default') $c.=$this->getSectionItems($section);
    }
    $c.=$this->getSectionItems($this->sections[$this->sectionLookup['default']]);
    // Output main and subform keys
    $c.=$this->outputKey();
    foreach ($this->subForms as $sf) { $c.=$sf->outputKey(); }
    return $c;
  }

  function getListHeadings($showMultiEditOptions=false) {
    $c="\n";
    foreach ($this->sections as $section) {
    	if ($section['sectionID']!='default') $c.=$this->getSectionHeadings($section,$showMultiEditOptions);
    }
    $c.=$this->getSectionHeadings($this->sections[$this->sectionLookup['default']],$showMultiEditOptions);
    // Get subForm headings
    // foreach ($this->subForms as $sf) { $c.=$sf->getListHeadings(); }
		if ($this->showEditButtons && isset($this->table)) $c.="<th>&nbsp;</th>";
    return $c;
  }

	// Used by getListHeadings()
  function getSectionHeadings($section,$showMultiEditOptions=false) {
    $c="";
    if (!$section['items'] || safeCount($section['items'])==0) return "";
    foreach ($section['items'] as $itemName=>$ok) {
      $item=$this->getItem($itemName);
			if ($item && $item->display) $c.=$item->getForHeading($showMultiEditOptions);
    }
    return $c;
  }

  function getListRow() {
    $c="\n";
    foreach ($this->sections as $section) {
    	if ($section['sectionID']!='default') $c.=$this->getSectionList($section);
    }
    $c.=$this->getSectionList($this->sections[$this->sectionLookup['default']]);
    // foreach ($this->subForms as $sf) { $c.=$sf->getListRow(); }
    return $c;
  }


	function getSectionList($section) {
		$c="";
    if (!$section['items'] || safeCount($section['items'])==0) return "";
    foreach ($section['items'] as $itemName=>$ok) {
      $item=$this->getItem($itemName);
      if ($item->display) {
        $val=$item->getForList();
        $style = $item->style;
        if (!$style) {
          $textAlign = $item->getTextAlign();
          $style = ((strlen($val)==7 && $val[0]=='#')?"background:".$val.";":false).(($item->hidden())?"display:none;":false).(($textAlign)?"text-align:".$textAlign.";":false);
        }
				$c.="<td".(($style)?" style='".$style."'":"")." class='".$item->getHtmlName()."Td'>".(($this->charLimit) ? cribBlurb($val, $this->charLimit) : $val)."</td>\n";
			}
		}
		return $c;
	}

  // Get the items in a form section
  function getSectionItems($section) {
    $c=false; $buttons=[];
    if (!$section['items'] || safeCount($section['items'])==0) return "";
    $partOfTable=($this->isMultiForm || $this->isSubForm);
    // Give sections a unique ID to enable CSS styling
    $id=escVar("ks".$section['sectionID']);
    foreach ($section['items'] as $itemName=>$ok) {
      $item=$this->getItem($itemName);
      if ($item) {
				if ($item->display) {
				  // Save buttons til the end
          // Pass over parent Form details
          $floatOver=((isset($item->floatOver))?$item->floatOver:false);
          $item->deleteConfirm=$this->deleteConfirm;
          $html=$item->get(false, $this->layout, $this->wrapInTable, $floatOver);
				  if ($item->coltype=='submit') {
				    array_push($buttons,$html);
				  } else {
  				  $c.=$html;
          }
				}
			} else {
				// e("Form section item ".$itemName." not found :(");
			}
    }
    if (!$partOfTable && $this->wrapInTable && $c) {
      $c="<table class='Table'>".$c."</table>";
    }
    if (safeCount($buttons)>0) {
      $c.="<div class='btnMoon'>";
      foreach ($buttons as $html) {
        $c.=$html;
      }
      $c.="</div>";
    }
    if (!$partOfTable && $section['sectionID']!="default" && $section['title']) {
      return $this->getSection($id,$section['title'],$c);
    }
    return $c;
  }

    function getSection($id,$head,$body=false,$headClass=false,$sectionClass=false) {
        $c="<div id='".$id."' class='Section expandable".(($sectionClass)?" ".$sectionClass:"")."'>";
        if ($head) {
            $togglable = (empty($this->sections) || safeCount($this->sections) > 2 || (safeCount($this->sections) == 1 && !empty($this->sections['default'])));
            $c .= "
                <div class='SectionHead".(($headClass)?" ".$headClass:"")."' ";
            if ($togglable)  $c .= "onClick='toggleSection(this)'";
            $c .= "> ".$head;
            if ($togglable) {
                $c .= "<div class='headArrow rotate noPrint'></div>";
            }
            $c .= "
                </div>
            ";
        }
        $body = $body ?: "&nbsp;";
        $c .= "
            <div class='SectionBody'>
                {$body}
            </div><!-- .SectionBody-->
        </div><!-- #{$id}-->
        ";
        return $c;
    }

  function getFormFoot() {
    $c = "</div>"; // .mainForm
    // Add in bespoke html
    if (sizeOf($this->htmlBlocks)>0) {
      foreach ($this->htmlBlocks as $htmlBlock) {
        $c.= $htmlBlock;
      }
    }
  	$c.= "\n</form>\n";
  	// Plumb in bespoke JS
  	if ($this->footerJS) $c.="<script>".$this->footerJS."</script>";
  	return $c;
  }

  // Gets a standard, 1 record form. Picks up any posted keyVal (or null/0 for a new entry), or you can pass your own
  // NOTE: Even if a call to getForm() follows after a call to save() (so the form is already full of posted info) the whole thing is re-loaded
  function getForm($keyVal=false) {
    if ($this->isList === false) $this->isList = 0; // Definitely not a list
    // When you have subForms, getForm() is ONLY called in the master form (so it controls things)
    $this->populateForm($keyVal); // Recursively populate this and other subForms
    $imgCol = $this->getSubForm("imgsForm");
    $this->addSubmits();
    $c="\n";
    $c.=$this->getFormHead();
    $c.=$this->getFormItems();
    $c.=$this->getFormFoot();
    return $c;
  }

	function getFrozenForm($keyVal=false) {
		$this->disable("*");
    $this->populateForm($keyVal); // Recursively populate this and other subForms
    $c="\n";
    $c.=$this->getFormHead();
    $c.=$this->getFormItems();
    $c.=$this->getFormFoot();
    return $c;
	}

  // Populate the current form with either:
  // a) new entry (with or without posted values)
  // b) existing entry from database
  // Also recursively populates subForms
  function populateForm($keyVal=false,$ignorePosted=false) {
    $ignorePosted=($ignorePosted || $this->ignorePosted);
    // SET THE PRIMARY KEY - based on what has been passed / posted. 0=New entry
    $this->getKeyVal($keyVal);
    // POPULATE THE FORM
    if ($this->keyVal==0 || strpos("".$this->keyVal,'socNew')===0) {
      // NEW form entry
      // By default we override any vars with those passed to the current page, but sometimes this functionality is not desired (e.g. when you want to display a completely fresh form after submission)
      if (!$ignorePosted) {
        // Load form from POSTED values (leaving free for defaults where blank)
        foreach ($this->cols as $col) { if ($col->display>0 && !$col->valSet) $col->pickUpVal(); }
      }
    } else {
      // EDIT the posted ID
      $this->clearDefaults(); // Clear defaults, they're not allowed
      // Load data into this form's columns
			$this->loadData(); // If it's not already been done by a call to getVal()...
 	 	}
 	 	// Link up & populate subForms using various joinTypes:
 	 	foreach ($this->subForms as $sf) {
      if ($sf->joinType=='PARENT' || $sf->joinType=='PARENT-EXTEND') {
     	 	// (a) PARENT (or PARENT-EXTEND) : subForm.subFormID = this.subFormID
        $sCol = $this->getCol($sf->keyCol);
        $sfKeyVal=$this->getVal($sf->keyCol);
        // Unset any set columns
   	 	  // e("Master form kicking off population of subform ".$sf->name." using join type ".$sf->joinType." KeyCol ".$sf->keyCol."=".$sfKeyVal);
        $sf->populateForm($sfKeyVal,$ignorePosted);
      } else if ($sf->joinType=='CHILD-EXTEND') {
     	 	// (b) CHILD (or CHILD-EXTEND) : subForm.thisFormID = this.thisFormID
        if ($this->keyVal==0) {
     	 	  $sf->populateForm(0,$ignorePosted);
        } else if ($sf->getVal($this->keyCol)==$this->keyVal) {
          // OK! Already linked up...
     	 	  $sf->populateForm($sf->keyVal,$ignorePosted);
        } else {
          // Find a subForm record that works for me
          $sfKeyVal=$this->DB->GetOne("SELECT ".$sf->keyCol." FROM ".$sf->table." WHERE ".$this->keyCol."=".nvl($this->keyVal,0));
     	 	  $sf->populateForm($sfKeyVal,$ignorePosted);
        }
      } else {
        echo "<h3>Sorry, joinType ".$sf->joinType." is not yet supported</h3>";
      }
 	 	} // Should bubble keys back up to the master form
  }

  /*
  // Called when this form is a subForm of another by the main form
  function getSubForm() {
    if (!$this->isSubForm) return ($this->isMultiForm)?$this->getMultiForm():(($this->isList)?$this->getList:$this->getForm());
    if ($this->isMultiForm) return "<td valign='top'><i>".$this->title."</i></td><td>".$this->getMultiFormTable()."</td>";
    if ($this->isList) return "<td valign='top'><i>".$this->title."</i></td><td>".$this->getListTable()."</td>";
    return $this->getFormItems();
  }
  */

  function getWhereAsHidden($where=false) {
    $where=($where)?$where:$this->where;
    if (!$where) return "";
    $c="";
    foreach (getPairs($where," AND ","="," IS ","'") as $col=>$val) { $c.="<input type='hidden' name='".$col."' value=".$val." />\n"; }
    return $c;
  }

	// Produce a multi-record Form for all the rows returned by this->where
	// Note: Saving will create a new entry UNLESS you have called setRequired()
  function getMultiFormTable($newRows=0,$hideDelete=false,$hideMultiEdit=false) {
    $this->isList = 2;
    $out="\n";
		$rowCount=0;
		if (!$newRows) { // Edit existing
			$sql=where("SELECT ".$this->keyCol." FROM ".$this->table,$this->where);
			$sql.=($this->orderBy)?" ORDER BY ".$this->orderBy:(($col=$this->getCol("ordering"))?" ORDER BY ordering":"");
			$existingRows=$this->DB->getAll($sql,1);
			$showMultiEditOptions=(!$newRows && !$hideMultiEdit && $existingRows && sizeOf($existingRows)>1);
		} else {
			// Make up some empty rows
			$existingRows=[];
			for ($n=0; $n<(int)$newRows; $n++) {
			  $existingRows[$n+1]=[0=>'socNew'.($n+1)];
			}
			$showMultiEditOptions=(!$hideMultiEdit && $n>1);
		}
		if ($existingRows) {
      $out.="
      <div class='dataTableContainer'>
        <table id='".$this->name."MultiTable' class='listTable'>
          <thead><tr>".$this->getListHeadings($showMultiEditOptions)."<th><!-- pk col --></th>
      ";
      if (!$newRows) {
        $out.=(($hideDelete || $this->suppressSubmit)?"<th>&nbsp;</th>":"<th style='text-align:center;'>Select</th>");
      }
      $out.="
          </tr>
        </thead>
        <tbody>
      ";
      // UPDATEABLE ROWS
      foreach ($existingRows as $row) {
        // Fill the form and subForms with data
        $this->clearVals(true);
        $this->setKeyVal($row[0]);
        $this->setPrefix($this->keyCol.$this->keyVal);
        $this->populateForm($row[0],true);
        $formItems = $this->getFormItems();
        $style = $this->getRowStyle();
        // Note: process formItems before outputting the row, as they may change it's colour
        $out.="<tr".(($style)?" style='".$style."'":"").">\n";
        $out.=$formItems;
        if (!$newRows) {
          $out.="<td style='background-color:#DDDDDD; color:black; text-align:center;'><input type='hidden' name='".$this->prefix.$this->keyCol."' value='".$this->keyVal."' />".(($hideDelete || $this->suppressSubmit)?"":"<input type='checkbox' value='Y' name='".$this->prefix."DELETE"."' />")."</td>";
        }
        $out.="</tr>\n";
        $rowCount++;
      }
      $out.="
          </tbody>
        </table>
      </div>
      ";
    }
    return $out;
  }

	// Multi-record form (restricted by $where, which MUST now be supplied)
  // $hideDelete removes delete boxes (used for history type forms when an end date is used instead)
	function getMultiForm($arrOrWhere=false,$showNew=1,$extraFields=false,$order=false,$hideDelete=false, $hideMultiEdit=false, $cssClass=false) {
		if (is_array($arrOrWhere)) {
			$where=getIfSet($arrOrWhere,'where',false);
			$showNew=getIfSet($arrOrWhere,'showNew',$showNew); $extraFields=getIfSet($arrOrWhere,'extraFields',$extraFields); $order=getIfSet($arrOrWhere,'order',$order); $hideDelete=getIfSet($arrOrWhere,'hideDelete',$hideDelete); $hideMultiEdit=getIfSet($arrOrWhere,'hideMultiEdit',$hideMultiEdit);
		} else {
			$where=$arrOrWhere;
		}
		$this->setMultiForm();
		if ($where) $this->where=$where;
		if ($order) $this->orderBy=$order;
    $cssClass= ($cssClass) ? " class='".$cssClass."'" : "";
		$out="\n";
		$colSpan=0;
		$bgCol="";
		// Add a <form> tag
		$out.="<form enctype='multipart/form-data'".$cssClass." name='".$this->name."' id='".$this->name."' method='post' action='".$this->submitTo."' ".(($this->autoComplete=='off')?"autocomplete='off'":"").">\n";
		if (!$this->isSubForm) {
			if (!$this->suppressSubmit) $out.="<tr><td colspan='4'><input class='btn' type='submit' name='submitBtn' value='Save All' />";
			if (!($hideDelete || $this->suppressSubmit)) $out.="<input class='btn' type='submit' name='submitBtn' value='Delete Selected'  onClick='return confirm(\"".$this->deleteConfirm."\");'/></td></tr>";
		}
		$out.="\n<input type='hidden' name='multiForm' value='1' />";
		$out.="\n<input type='hidden' name='socTable' value='".$this->table."' />";
		if ($this->addAction) $out.="<input type='hidden' name='".$this->addAction."' value='".$this->action."' />";
		if ($extraFields) $out.=$extraFields;
		// Explicitely add in parameters from where clause, so that these are also passed to the handling page
		$out.=$this->getWhereAsHidden($this->where);
		if ($where) $out.=$this->getMultiFormTable(0,$hideDelete,$hideMultiEdit);
		if ($showNew) {
		  $out.="<hr /><div class='newEntry'><b><i>&LessLess; New ".(($showNew>1)?"Entries":"Entry")." &GreaterGreater;</i></b>";
			// BLANK ENTRY TABLE
			$out.=$this->getMultiFormTable($showNew,1,$hideMultiEdit);
			$out.="</div> <!-- .newEntry -->";
		}
		if (!$this->isSubForm) {
			if (!$this->suppressSubmit) $out.="<input class='btn' type='submit' name='submitBtn' value='Save All' />";
			if (!($hideDelete || $this->suppressSubmit)) $out.="<input class='btn' type='submit' name='submitBtn' value='Delete Selected'  onClick='return confirm(\"".$this->deleteConfirm."\");'/>";
		}
		$out.="</form>";
		return $out;
	}

	// Set $this->editPage (to e.g. editProduct.php) to link up getList() edit links to a page other than the current
  function getEditPage() { return ($this->editPage)?$this->editPage:getPageUrl(); }

  function handleSubmit() {
    if ($this->saved || $this->deleted) {
      // Do not submit if we have already done so
      return false;
    }
    if (p("socTable")!=$this->table) {
      return false; // Nothing to do
    }
    if (buttonPressed("Save")) {
      return $this->save();
    } else if (buttonPressed("Delete")) {
      return $this->delete();
    }
    return false;
  }

  function isListPage() {
    // If a list or form has been invoked, we know for sure
    if ($this->isList!==false) return $this->isList;
    // Take a guess...
    return (p($this->keyCol)===false || p("submitBtn")!==false);
  }

  // Automatically get either an HTML form or a list table, saving if necessary
  function get() {
    $this->handleSubmit();
    if ($this->isListPage()) {
      return $this->getList();
    }
    return $this->getForm();
  }

	// View only list (with optional edit buttons)
	function getList($class='listTable') {
    $this->isList = 1;
    if ($this->where && strpos($this->where,"SELECT")!==false) return $this->getSqlList($this->where);
		$out="\n";
 		$out.=$this->getListTable($class);
		// Blank entry
		if ($this->showEditButtons && isset($this->table)) {
			// Derive the singular name for a record from the primary key column name e.g. for the classes table this is 'class'
			$this->singular=substr($this->keyCol,0,strpos($this->keyCol,"ID"));
			// Give a new button
      $out.="<table class='".$class."'>";
			$out.="<tr><td colspan=4>";
			$out.="<a href='".$this->getEditPage()."?".$this->keyCol."=0".(($this->where)?"&".str_replace(' AND ','&',$this->where):"")."' class='btn'>NEW ".unCamel($this->singular)."</a>";
			$out.="</td></tr>\n";
      $out.="</table>\n";
		}
		return $out;
	}

  function getListTable($class='listTable') {
    $this->isList = 1;
    $c="\n\n";
    $sql=where("SELECT ".$this->keyCol." FROM ".$this->table,$this->where);
		$sql.=($this->orderBy)?" ORDER BY ".$this->orderBy:(($col=$this->getCol("ordering"))?" ORDER BY ordering":"");
    if ($this->pagination) {
    	$count=$this->DB->GetOne(where("SELECT COUNT(1) FROM ".$this->table,$this->where));
    	$curPage=np("page",0);
    	$sql.=" LIMIT ".($curPage*$this->pagination).",".$this->pagination;
    } else if ($this->limit) {
    	$sql.=" LIMIT ".$this->limit;
    }
    if ($this->pagination) {
    	$c.="<div class='fkpagination'>";
			if ($curPage>0) $c.="<a href='".$this->submitTo."?page=".($curPage-1)."'>&lt;</a> | ";
			$maxPage=ceil($count/$this->pagination);
    	for ($n=0; $n<$maxPage; $n++) {
    		if ($n>0) $c.=" | ";
    		$c.="<a href='".$this->submitTo."?page=".$n."'>".($n+1)."</a>";
    	}
			if ($curPage<($maxPage-1)) $c.=" | <a href='".$this->submitTo."?page=".($curPage+1)."'>&gt;</a>";
    	$c.="</div>";
    }
		$c.="<table class='".$class." Table' id='".$this->name."ListTable'>\n";
		// THE LIST
		$stripe="class='stripe'";
		// HEADINGS
		$c.="<thead><tr>".$this->getListHeadings(false,$this->showEditButtons)."</tr></thead><tbody>\n";
		// Display the list's rows
		foreach ($this->DB->getAll($sql,1) as $row) {
			// Fill the form and subForms with data
			$this->clearVals(true,true,false);
		  $this->populateForm($row[0],true); // !! This also needs to fill the subForm
      // Note: process row items before outputting the row, as they may change it's colour
      $r = $this->getListRow();
      $style = $this->getRowStyle();
			$c.="<tr".(($style)?" style='".$style."'":"")." id='".$this->keyCol.$row[$this->keyCol]."Tr'>\n";
      $c.=$r;
      if ($this->showEditButtons && isset($this->table)) {
				// Give an edit button for this row
        if($this->table=='stock' && in($row['stockID'],"100,101,102,103,104,105")) {
          $c.="<td><span class='btn'>-</span><br /></td>\n";
        } else {
          $c.="<td><a href='".$this->getEditPage()."?".$this->keyCol."=".$row[$this->keyCol]."' class='btn'>edit</a><br /></td>\n";
        }
			}
			$c.="</tr>\n";
		}
		$c.="</tbody></table>";
		return $c;
  }

  // Similar to getList() but runs not on the current this->table, but the SQL given (joins n all)
  function getSqlList($sql,$keyCol=false,$charLimit=false) {
		if ($charLimit) $this->charLimit=$charLimit;
		$out="\n";
		$colSpan=0;
		$out.="\n\n<table class='listTable' id='".$this->name."SqlListTable'>\n";
		$stripe="class='stripe'";
		// HEADINGS
		$out.="<thead><tr>\n";
    $row=$this->DB->getRow($sql); // Get the first row to find out what data is contained in this SQL statement
    if(!$row) return "<p><i>No records</i></p>";
    foreach ($row as $colName=>$val) { if (!isnum($colName)) { $colSpan++; $out.="<td style='font-weight:bold;'>".$colName."</td>\n"; } }
		$out.="</tr></thead><tbody>\n";
		// Display the list's rows
		foreach ($this->DB->GetAll($sql) as $row) {
      $style = $this->getRowStyle();
			$out.="<tr".(($style)?" style='".$style."'":"").">\n";
      foreach ($row as $colName=>$val) {
        if (!isnum($colName)) {
          $style = ((strlen($val)==7 && $val[0]=='#')?"background:".$val.";":false);
          $out.="<td".(($style)?" style='".$style."'":"").">".(($this->charLimit) ? cribBlurb($val, $this->charLimit) : $val)."</td>\n";
        }
      }
			if ($this->showEditButtons && $keyCol) {
				// Give an edit button for this row
				$out.="<td><a href='".$this->getEditPage()."?".$keyCol."=".$row[$keyCol]."' class='btn'>edit</a><br /></td>\n";
			}
			$out.="</tr>\n";
		}
		// Blank entry
		if ($this->showEditButtons && $keyCol) {
			// Derive the singular name for a record from the primary key column name e.g. for the classes table this is 'class'
			$this->singular=substr($keyCol,0,strpos($keyCol,"ID"));
			// Give a new button
			$out.="<tr><td colspan='".$colSpan."'>";
			$out.="<a href='".$this->getEditPage()."?".$keyCol."=0".(($this->where)?"&".$this->where:"")."' class='btn'>NEW ".unCamel($this->singular)."</a>";
			$out.="</td></tr>\n";
		}
    $out.="</tbody></table>\n";
		return $out;
  }

  function addAction($actionParameterName="a",$action=false) { $this->addAction=$actionParameterName; $this->action=($action)?$action:p("a",p("Aa"));}

	/***********************************************************************************************
  + Form Submission functions
  +
  + These functions deal with Forms once they are posted. Handles things such as validation,
  + clearing a form and saving a form.
  ***********************************************************************************************/

  // Validates all fields that are set to be validated in their column definition.
  function validate() {
    $valid = true;
    foreach ($this->cols as $col) {
      if ($col->display==2 && !$col->validate()) $valid=false;
    }
    return $valid;
  }

  // Decides what should happen now that the form has been submitted. Submit button value determines which condition is hit
  // RedirPage is url to go to once update/insert/delete is finished. Where clause is needed when multiForms are used e.g. "schoolID=1"
	function handleSubmission($redirPage=null) {
		if (buttonPressed('cancel')) return $this->intelligentRedir($redirPage);
		// Check this is the right Form for the submission
		if (p("socTable")!=$this->table) return false;
		$ok=false;
		// Multi-form
		if (buttonPressed('save all')) {
			if (!$this->where) {
				e("WARNING: No where clause passed to handleSubmission() - skipping multiSave");
			} else {
			  $ok=$this->handleMultiSave($this->where);
			}
		} else if (buttonPressed('delete selected')) {
		  $ok=$this->handleMultiDelete($this->where);
		} else {
      // Single form (+ multi-form new record)
      if (buttonPressed('save') || buttonPressed('add')) $ok=$this->save();
      if (buttonPressed('delete')) $ok=$this->delete();
      if (buttonPressed('clear')) $ok=$this->handleClear();
    }
		if ($ok) { $this->intelligentRedir($redirPage); return $ok; }
		return false; // If we are here, no submission was made, so just continue as normal
	}

	// If no explicit redirect is given, work out where to redirect to - if at all.
	function intelligentRedir($redirPage=null) {
    if ($redirPage===false) return false;
  	$this->redirPage=(isset($redirPage))?(($redirPage=="NONE")?false:$redirPage):(isset($this->redirPage)?$this->redirPage:0);
  	if ($this->redirPage && $redirPage!=-1) {
  		if ($this->jsSubmitCode) { popRedir($this->redirPage,true); } else { redir($this->redirPage); }
  	}
  	// e("No redir page :(");
	}

  // The Clear submit button wipes a file record
  function handleClear() {
    // ! Do not clear subForms!
    $fieldSql="";
		if ($this->keyVal>0) {
			$sql="UPDATE ".$this->table." SET ";
			foreach($this->cols as $col) {
				if ($item->process) {
					// ONLY clear file and text fields
					if ($col->coltype=='file' or $col->coltype=='image' or $col->coltype=='text') $fieldSql.=$col->name."='',";
				}
			}
			if (notnull($fieldSql)) $fieldSql=strrev(substr(strrev($fieldSql), 1));
			$sql.=$fieldSql." WHERE ".$this->keyCol."=".$this->keyVal;
      $result=$this->DB->execute($sql);
		}
  }

  // Delete all records by retrieving all the records that have been displayed and looping over it to reference the posted vars. Makes things safer.
  function handleMultiDelete($where=null) {
		$this->where=(isset($where))?$where:$this->where;
		$sql=where("SELECT * FROM ".$this->table,$this->where);
		$originalRecords=$this->DB->GetAll($sql);
		foreach ($originalRecords as $row) {
			$this->keyVal=$row[$this->keyCol];
			// Delete?
			$this->setPrefix($this->keyCol.$this->keyVal);
			if (p($this->prefix."DELETE")) $this->DB->execute("DELETE FROM ".$this->table." WHERE ".$this->keyCol."=".$this->keyVal);
		}
		return true;
  }

  function delete($keyVal=false,$alsoDeleteSubForms=true,$alsoDeleteFiles=true) {
    // SET THE PRIMARY KEY - based on what has been passed / posted. 0=New entry
    $this->keyVal=($keyVal!==false)?$keyVal:(($this->keyVal!==false)?$this->keyVal:np($this->keyCol,0));
    if (!$this->keyVal) return false; // You can't delete a keyVal=0
    // Load up the subform data (we're only interested in the keys for deletion)
    $this->populateForm($this->keyVal);
    // Delete subForms first...
    foreach ($this->subForms as $sf) {
      // Children all get whacked, as do EXTENDed records (including PARENT-EXTENDs)
      // DON'T delete PARENTs though (this is a primary difference between the PARENT and PARENT-EXTEND joinTypes ;))
      if ($alsoDeleteSubForms && in($sf->joinType,'CHILD,CHILD-EXTEND,PARENT-EXTEND')) {
        $sf->delete();
      } else if ($sf->getCol($this->keyCol)) {
        // If not deleting subForm records, null any reference to this form's key...
        $this->DB->execute("UPDATE ".$sf->table." SET ".$this->keyCol."=NULL WHERE ".$sf->keyCol."=".$sf->keyVal);
        $sf->setVal($this->keyCol,null); // Leave SF record in place and update it... don't really know why {seems tidy}
      }
    }
    // Handle file delete, re-ordering and translations
    if ($alsoDeleteFiles) {
      foreach ($this->cols as $col) {
        if ($col->process) {
          // ! Handle multifile deletes
          if ($col->coltype=='file' or $col->coltype=='image') {
            $col->handleFileDelete();
            $col=$this->reorderFields($col);
          }
        }
      }
		}
    // Actually do the DELETE
		$this->DB->execute("DELETE FROM ".$this->table." WHERE ".$this->keyCol."=".$this->keyVal);
		$this->clearVals(true,true); // Clear out this form
    $this->deleted = $this->keyVal;
		return true;
  }

	// Attempt to upsert multiple records setting up Form for each row and calling save(), multiple times
	// Gain speed by passing the original where
	function handleMultiSave($where=null) {
		$ids=false; // List of saved ids, including newly created ones
		$this->isMultiForm=true;
		$rowNum=0;
		// Save updated existing records
		if ($where) {
			$this->where=(isset($where))?$where:$this->where;
			$sql=where("SELECT ".$this->keyCol." FROM ".$this->table,$this->where);
			$pks=$this->DB->GetAll($sql,0);
			foreach ($pks as $row) {
				$pk=$row[0];
				if (np($this->keyCol.$pk.$this->keyCol)>0) { // Double-check this row exists in posted data
					// For each row submitted, i) clear the Form ii) pickup the new row's submitted data iii) process it!
					$this->clearVals(true,true);
					$this->setKeyVal($pk);
					$this->setPrefix($this->keyCol.$this->keyVal);
					$id=$this->save();
					if ($id) $ids=addTo($ids,$id);
				}
			}
		}
		// Handle new entries
		// First, search for new entries
		$maxN=0;
		// behaviourCategoryIDsocNew1category
		foreach ($_POST as $var=>$val) { $n=locate("socNew",$var); if (notnull($val) && $n!==false) { $maxN=max($maxN,$n); } }
		if ($maxN>0) {
			for($n=1;$n<=(int)$maxN;$n++) {
				$this->clearVals(true,true);
				$this->setPrefix($this->keyCol."socNew".$n);
				$this->setKeyVal(0);
				$id=$this->save();
				if ($id) $ids=addTo($ids,$id);
				$this->multiSaveExtra($ids);
			}
		}
		return $ids;
	}

  // Overload this stub function to do extra processing on a multi-save
  function multiSaveExtra($pks) { }

  function isColReady($col) {
    // Read only are only processed for save on insert, not update
    if ($col->readOnly) $col->process = ($this->keyVal>0)?0:1;
    return $col->setReady($col->process && !(in(strtolower($col->coltype),'file,image,multiimage,multifile,submit,timestamp')));
  }

	// If a table is set, will attempt to insert/update the columns in Cols
  function save() {
    $this->getKeyVal();
    // Save subForms first...
    foreach ($this->subForms as $sf) {
      // For safety, link sf to this record as much as is possible right now (for this->KeyVal=0 we'll have to relink later on anyhow...)
      if ($sf->joinType=='CHILD' || $sf->joinType=='CHILD-EXTEND') {
        $sf->setVal($this->keyCol,$this->keyVal);
      }
      $sfPK=$sf->save();
      // Transfer sf primary key back to this table
      if ($sf->joinType=='PARENT' || $sf->joinType=='PARENT-EXTEND') {
        $this->setVal($sf->keyCol,$sfPK);
      }
    }
    // Save this form (only process=true columns get updated. If a new record, use defaults where not posted)
  	if (!(isset($this->table))) return false;
  	// Tag columns to process (e.g. leave files and translated columns for now)
		foreach($this->cols as $col) {
      if ($this->isColReady($col) && !$col->valSet) $col->pickUpVal(); // Load any post data into this field
		}
		if (!$this->checkRequiredFields()) return false;
		if ($this->keyVal==0) {
		  $this->keyVal=$this->DB->doInsert($this->table,$this->getColPairs());
		  $this->inserted=true;
		} else {
      $this->clearDefaults(); // Clear defaults, they're not allowed for an already saved record
      $this->DB->doUpdate($this->table,$this->getColPairs(),$this->keyVal,$this->keyCol);
      $this->inserted=false;
		}
    // Handle file upload, .html page creation, ordering and translations
		foreach ($this->cols as $col) {
		  if ($col->process) {
		    // Update our key on entries created by the AJAX file upload
  		  if (in($col->coltype,'multifile,multiimage')) {
          if (($tableInfo=$col->getMultiFileTable()) && $this->inserted) {
            $this->DB->execute("UPDATE ".$tableInfo['table']." SET ".$this->keyCol."=".$this->keyVal." WHERE ".$this->keyCol."=0 AND sessionID=".fss(session_id()));
          }
				} else if ($col->coltype=='file' || $col->coltype=='image') {

					// Handle standard file upload
					if (p($col->getHtmlName()."Pref")=="clear") {
						$this->DB->execute("UPDATE ".$this->table." SET ".$col->name."=NULL WHERE ".$this->keyCol."=".$this->keyVal);
						$col->clearVal();
					} else if ($col->handleUpload()) {
						$this->DB->doUpdate($this->table, array($col->name=>$col->getForSQL()), $this->keyVal, $this->keyCol);
					}
				}
				// Handle .html page creation
				if ($col->name=="dedicatedUrl") $this->handleSeoPageCreation($col->getVal());
				// Handle ordering
				$col = $this->reorderFields($col);
			}
		}
		// Go back and update this PK on subForms which feature it
    foreach ($this->subForms as $sf) {
      if ($sf->joinType=='CHILD' || $sf->joinType=='CHILD-EXTEND') {
        $sql="UPDATE ".$sf->table." SET ".$this->keyCol."=".$this->keyVal." WHERE ".$sf->keyCol."=".$sf->keyVal;
        $this->DB->execute($sql);
      }
    }
    // Handle join tables last (all keys now being up to date)
    foreach ($this->joinCols as $jc) {
      $jc->save();
    }
    $this->saved = $this->keyVal;
		return $this->keyVal;
	}

	/***********************************************************************************************
  + Emailing functions
  +
  + Methods to deal with emailing of templated emails along with standard plaintext and html emails.
  + Deals with the emailForms submission
  ***********************************************************************************************/

  // Just pass in table based html with inline styles or font tags. Gmail etc will strip them otherwise.
	function sendEmailFrom($fromAddr,$toAddr,$subj,$body="",$ccAddr=null,$testMode=false,$logMode=false) {
		// set who the email addresses is sent from
		ini_set("sendmail_from", $fromAddr);
		// $headers="\r\n";
		$headers="From: ".$fromAddr."\n";
		$headers.="Reply-To: ".$fromAddr."\n";
		if (isset($ccAddr)) $headers .="Cc: ".$ccAddr."\n";
		// To send HTML mail, the Content-type header must be set
		$headers.="MIME-Version: 1.0\n"; /* v.important NOT to put \n at the end as Outlook ends up escaping the WHOLE header */
		// $headers.="Content-type: text/plain; charset=iso-8859-1\n";
		$headers.="Content-Type: text/html; charset=ISO-8859-1\n";
    $headers.="Content-Transfer-Encoding: 8bit;\n\n";
		if ($testMode) return testMail( $toAddr, $subj, $body, $headers );
		$success=mail( $toAddr, $subj, $body, $headers );
		if ($logMode) $this->DB->execute("INSERT INTO emails(status,toAddr,fromAddr,headers,subject,body) VALUES ('".(($success)?"OK":"FAIL").(($testMode)?"-TEST":"")."','".escSQL($toAddr)."','".escSQL($fromAddr)."','".escSQL($headers)."','".escSQL($subj)."','".escSQL($body)."')");
		return $success;
	}

	function sendEmail($toAddr,$subj,$body="",$ccAddr=null,$testMode=false,$logMode=false) {
    if (!($fromAddr=$this->DB->getParameter("fromAddr"))) {
  		if (!($fromAddr=$this->DB->getParameter("defaultEmail"))) {
  			$fromAddr="postman@home.co.uk!";
  		}
		}
	  return $this->sendEmailFrom($fromAddr,$toAddr,$subj,$body,$ccAddr,$testMode,$logMode);
	}


	function sendTestMail($toAddr,$subj,$body,$ccAddr=null) { $this->sendEmail($toAddr,$subj,$body,$ccAddr,true); }
	function sendTextMail($toAddr,$subj,$body="",$ccAddr=null,$testMode=false,$logMode=false) { return $this->sendEmail($toAddr,$subj,$body,$ccAddr,$testMode,$logMode); }
	function sendHtmlMail($toAddr,$subj,$body,$ccAddr=null,$testMode=false,$logMode=false) { return $this->sendEmail($toAddr,$subj,$body,$ccAddr,$testMode,$logMode); }


  // The master method that takes a emailFormID and spits out a ready to go customer form (e.g. contact form)
	function getEmailForm($emailFormID){
    $output = $this->setupEmailForm($emailFormID);
    return $output;
	}

	// Decides whether the form is from a table or if it is a custom form generated from the valid fields tables. Returns false if the form has not been submitted, or has not validated
	function handleEmailFormSubmission($emailFormID){
		if (!(p("emailFormID")==$emailFormID)) return false; // it hasn't been submitted!
    $emailForm=$this->DB->GetRow("SELECT * FROM emailForms WHERE emailFormID = '". $emailFormID . "'");

    if ($emailForm['skipValidation']=='N' && !$this->validate()) return false; // Validation was not successful, return to getCEmailForm component to redisplay form with errors
    if ($emailForm['tableOrString']=='T') $keyID=$this->save(); // If this has been created from a tables DD then we should save the data to that table.

    $staffEmail = $this->emailFormToStaff($emailForm);

		if($staffEmail != -1 && $emailForm['emailCustomer'] == 'N')	return true;

		$cusEmail = $this->emailFormToCustomer($emailForm);

		if($staffEmail != -1 && $cusEmail != -1) return true;
		echo "Sorry, there was an issue sending this information".$emailForm['emailStaff'];
		return false;
	}

	/***********************************************************************************************
  + Sub Functionality functions
  +
  + These functions remove a lot of code that would otherwise clutter the intentions of the above
  + methods with unnecessary information. The function name should explain the function it provides.
  ***********************************************************************************************/

	// Special form from db generation methods. Takes a field from the email forms table and spits out a form from the data in the field.
  function setupEmailForm($emailFormID) {
    $form = new AutoForm();
    if ($this->emailFormID==$emailFormID) return true; // This emailForm is already set-up
    $form->emailFormID=$emailFormID;
    $emailForm=$this->DB->GetRow("SELECT * FROM emailForms WHERE emailFormID = '". $emailFormID . "'");
    // Build emailForm from a table (just use Form to do this with a couple additions...)
    $form->wrapInTable=($emailForm['wrapInTable']=='N') ? false : true;
    if ($emailForm['tableOrString']=='T') {
      $form->useTable($emailForm['tableSource']);
      $form->ignoreAllBar($emailForm['validFields']);
    // Build emailForm from Sam's String Stuff...
    } else {
      $form->useStringDef($emailForm['validFields']);
    }

    $form->addSubmit("Send");
    $form->add('emailFormID', ['coltype'=>'hidden']);
    $form->setVal('emailFormID',$emailForm['emailFormID']);
    return $form->getForm();
  }

	public function emailFormToCustomer($emailForm){
    if ($emailForm['emailCustomer'] == 'Y') {
			$customerEmail='';
			$html = '';
      foreach ($this->cols as $col) {
				$html .= $col->name . ' : ' . $col->val . "<br />";
				if ($col->name=='email') $customerEmail=$col->val;
		  }

			$html = ($emailForm['includeCustomerData'] == 'Y') ? $emailForm['customerBody']."<br />" . $html : $html;
			if (notnull($html) && $customerEmail) {
				if ($this->sendEmailFrom($fromAddr,$customerEmail, $emailForm['customerSubject'], $html, null, $testmode)) return 1;
			   return -1;
			}
		}
		return 0;

	}

	public function emailFormToStaff($emailForm){
	  $testmode=false;
	  $fromAddr=nvl($this->DB->getParameter("defaultEmail"),"postman@home.co.uk");
    if ($emailForm['emailStaff'] == 'Y') {
			$html=$emailForm['staffBody']."<br />";
			foreach ($this->cols as $col) {
				$html .= $col->name . ' : ' . $col->val . "<br />";
			}
			if ($this->sendEmailFrom($fromAddr,$emailForm['staffEmailTo'], $emailForm['staffSubject'], $html, null, $testmode))	return 1;
			return -1;
		}
		return 0;
	}

	function getColPairs() {
	  $pairs=[];
		foreach ($this->cols as $col) { if ($col->ready) $pairs[$col->name]=$col->getForSQL(); }
		return $pairs;
	}

	function reorderFields($col){
	  // Handle re-ordering
    if (($col->coltype=='ordering')) {
    	// Re-org all of the relevant rows (the CWC is in col->extra)
    	$rowsToReorder=$this->DB->GetAll(where("SELECT * FROM ".$this->table." WHERE ".$this->keyCol."!=".$this->keyVal." ORDER BY ordering",$col->extra));
    	// If our ordering is larger than the set, our ordering needs adjusting down to place it at the end of the set
    	if ($col->val>safeCount($rowsToReorder)) {
    		$col->val=safeCount($rowsToReorder)+1;
    		$this->DB->execute("UPDATE ".$this->table." SET ordering=".$col->val." WHERE ".$this->keyCol."=".$this->keyVal);
    	}
    	// Unefficiently (reassuringly) everyone gets a new ordering whether they likes it or not
    	for($n=0; $n<(safeCount($rowsToReorder)); $n++) {
    		// Note: ordering is indexed from 1, so all need boosting by at least 1 and those ABOVE our row by 2
    		$o=$n+1; if ($o>=$col->val) $o++;
    		$this->DB->execute("UPDATE ".$this->table." SET ordering=".($o)." WHERE ".$this->keyCol."=".$rowsToReorder[$n][$this->keyCol]);
    	}
    }
    return $col;
	}


  //Make this a little more generic to allow for multiple tag replacements.
  function checkOutputForTags($html, $tag){
		while(strpos($html, "##formID=") != false){
			$pos=strpos($html, "##formID=");
			$endpos = strpos($html, "##", $pos + 9);
			$formID = substr($html, $pos + 9, $endpos - ($pos +9));
			$formhtml = $this->getEmailForm($formID);
			$html = substr($html, 0,$pos) .$formhtml . substr($html, $endpos + 2);
		}

		return $html;
	}

  function setRowStyle($s, $permanent = 0) {
    $this->rowStyle = $s;
    $this->permanentRowStyle = $permanent;
  }

  function setRowColour($c, $permanent = 0) {
    $this->setRowStyle("background-color:".$c, $permanent);
  }

  function getRowStyle() {
    $style = false;
    if ($this->rowStyle) {
      $style = $this->rowStyle;
      if (!$this->permanentRowStyle) {
        $this->rowStyle = false;
      }
    } else {
      $colour = ($this->getAlt())?"#f5f5f5":"#e9e9e9";
      $style = "background-color:".$colour;
    }
    return $style;
  }
  
	// Flip-flops between true and false, handy for alternating colours etc.
  function getAlt() { return $this->alt=(!isset($this->alt) || !$this->alt); }

	// Clear down the existing form for a new entry (prevents values being picked up from GET/POST/DB)
	function newEntry() {
		$this->keyVal="0";
		foreach ($this->cols as $col) { $col->valSet=false; }
    // Re-configure subForms
    foreach ($this->subForms as $sf) { $sf->newEntry(); }
	}

  // function hashPassword($username,$password,$userID) { return hashData($username.$password.$userID); }

	// -------------------
	// DATA LOAD FUNCTIONS
	// -------------------
  function getLoader() {
    $c="\n";
    $c.=$this->getFormHead();
    $c.="<input type='file' name='loaderFile' /><input class='btn' type='submit' name='submitBtn' value='Load' />";
    $c.=$this->getFormFoot();
    return $c;
  }

  // Load a CSV file submitted via getLoader() into the current table
  function handleLoad($fileField="loaderFile") {
    $loaderCol=new Col($this->DB, 1,$fileField,["extra"=>"file"]);
    $loaderCol->handleUpload(); // Get the uploaded file into somewhere usable on the filesystem
    $filename=$loaderCol->getVal();
    $this->doLoad($filename);
  }

  // Load a CSV file from the filesystem into the current table
  // Returns the number of rows loaded
  function doLoad($filename) {
    // Load the file into this->cells (a big array of strings)
    $this->loadCSV($filename);
    // Analyse the column headings
    $rowNum=0;
    $headings=[];
    for ($colNum=0; $colNum<$this->colCount;$colNum++) {
      $cell=(isset($this->cells[$rowNum][$colNum]) && notnull($this->cells[$rowNum][$colNum]))?$this->cells[$rowNum][$colNum]:false;
      if ($cell) $headings[$cell]=$colNum; // Save a mapping of colName => colNumInFile
    }
    // We only want to process the headings that match actual columns
    $this->ignoreAllBar($this->DB->keyCollapse($headings,-1));
    // Rattle through inserting all other rows
    for ($rowNum=1; $rowNum<$this->rowCount; $rowNum++) {
      $this->clearVals(true,true);
			$this->setKeyVal(0);
			$hasData=false;
			foreach ($this->cols as $col) {
			  if (!isset($headings[$col->name])) {
			    e("No heading for ".$col->name);
			  } else {
          $colNum=$headings[$col->name];
          $cell=(isset($this->cells[$rowNum][$colNum]) && notnull($this->cells[$rowNum][$colNum]))?$this->cells[$rowNum][$colNum]:false;
          if ($this->isColReady($col) && $cell) { $col->setVal($cell); $hasData=true; }
        }
			}
			if ($hasData) $this->DB->doInsert($this->table,$this->getColPairs());
    }
    return $rowNum;
  }

	// Load the given filename into this->cells (a big array of strings)
	function loadCSV($filename,$upToRow=-1) {
    $eolCharsToTry=array(-1,13,10,11,12); // OS specific special characters mean PHP often fails to recognise new lines, so attempt alternative ways of reading the file
    foreach ($eolCharsToTry as $eolChr) {
    	// e("Trying load using eolChr ".$eolChr." - going up to row ".$upToRow);
      $this->rowCount=$this->attemptLoad($filename,$upToRow,$eolChr);
      if (($upToRow==1 && $this->rowCount>0) || $this->rowCount>1) { $this->eolChr=$eolChr; return true; }
    }
    return false; // No EOL char found
  }

  // Attempt to load a CSV file using the built-in PHP fgetcsv() method
  // Can alternatively be called with an ascii character code for the end-of-line char
  function attemptLoad($filename,$upToRow=-1,$eolChr=-1) {
    $this->cells=[]; $numRows=0; $this->colCount=0;
    if (!file_exists($this->getUploadDir().$filename)) return false;
    $file=fopen($this->getUploadDir().$filename,"r") or exit("Unable to open file ".$filename);
    $line=($eolChr==-1)?fgetcsv($file):$this->getLine($file,$eolChr);
    $safety=0;
    while ($line && ($upToRow==-1 || ($upToRow>-1 && $numRows<$upToRow)) && $safety++<$this->maxLineLimit) {
      $dataCell=0;
      foreach ($line as $cell) {
        $cell=utf8_encode($cell);
        $this->cells[$numRows][$dataCell++]=$cell;
      }
      if ($dataCell>1) { $numRows++; $this->colCount=max($this->colCount,$dataCell); } // NB. Keep numRows pointer static to ignore empty lines
      // Get a new line
      $line=($eolChr==-1)?fgetcsv($file):$this->getLine($file,$eolChr);
    }
    if ($safety>=$this->maxLineLimit) e("Form.attemptLoad() ran out of road after processing ".$this->maxLineLimit." lines");
    fclose($file);
    return $numRows;
  }

  // Fall-back version of fgetcsv for situations where the end-of-line chr is non-standard
  function getLine($file,$eolChr=false,$delimiter=",",$quoteChr='"') {
    $line="";
    $safety=0;
    while ($safety++<99999 && ($c=fgetc($file))!==false && $c!=chr($eolChr)) { $line.=$c; }
		if (!$quoteChr) $quoteChr=getQuoteChr($line);
		if (!($line)) return false;
    // if ($fixUnescapedSlashes) $line=str_replace("\"\\\"","\"\\\\\"",$line);
		if ($safety>=99999) e("Form.getLine() using eolChr[".$eolChr."] ran out of road at ".$safety." characters");
		return str_getcsv($line,$delimiter,$quoteChr);
  }

} // END OF Form CLASS

/*
 *
 * COL CLASS
 *
 */
class Col {

  var $version="21.08";
  var $type='Col';
  var $display=2; // Used when displaying the form: 0=Not-on-form, 1=Hidden, 2=Displayed
  var $hidden=false;
  var $process=true; // Used when saving the form: false=Do-not-process, true=Process
  var $disabled=false; // true=do not update (but display a disabled HTML field)
  var $readOnly=false; // true=do not update (but display as boiler plate text)
  var $require=false; // Is this a required field? If set to true, will prevent form submitting if blank (or invalid)
  var $disableAutoComplete = 0;
  var $table; // DB table this column is associated with (handed down from parent Form)
  var $prefix="";
  var $coltype="text"; // default to <input type='text' />
  var $formatAsString=false; // Whether the SQL created should escape this field as a string
  var $allowHtml=false; // Whether basic posted HTML tags are allowed
  var $title=false;
  var $class=false; // additional CSS class for form element
  var $tdClass=false; // CSS class for <td> wrapper

  /* Adjustable params */
  var $extra=false;
  var $options=false; // used to come in via $extra
  var $size=false; // sets <input size=x>
  var $cols=false; // sets <textarea cols=x>
  var $rows=false; // sets <textarea rows=y>
  var $plainText=false;
  var $maxChars=false;
  var $noTinyMce=false;
  var $imageTable=false;
  var $fileTable=false;
  var $imageCol=false;
  var $fileCol=false;
  var $noResizeIfOriginalSmaller=false;
  var $resizeWidth=false;
  var $resizeHeight=false;
  var $resizeMethod="STRETCH";
  var $thumbWidth=false;
  var $thumbHeight=false;
  var $thumbMethod=false;
  var $usePkAsFileName=false;
  var $overwrite=false; // Allow uploaded files to overwrite existing files with the same name
  var $js=false;
  var $cwc=false; // Constraining Where Clause - used for ordering fields
  var $html=false;
  var $default=false; // Set a default when creating a new record to pre-fill a column
  var $highlight=false;

  var $formName="";
  var $isSubForm=false;
  var $ownerKeyCol=false;
  var $ownerKeyVal=false;
  var $validFileExtensions="jpg,jpeg,gif,png,tiff,tif,psd,pdf,bmp,xml,pages,doc,docx,ppt,pptx,dot,ami,pages,xls,xlsx,txt,rtf,csv,ctf,vsd,css,json,mp3,aac,wav,sib,midi,m4a,mov,avi,flv,dat";
  var $validImageExtensions="jpg,jpeg,gif,png";
  var $sizeLimit=0;
  var $val=null;
  var $valSet=false; // Use this to check for the field value as opposed to testing $val for null, as a posted null is a legimate value
  var $keepClear=false; // Stop any value being set
  var $autoSubmit=false; // true=add js to select cols to auto-submit onChange
  var $currency; // Override to EUROS or USD - otherwise defaults to $_SESSION['currency'] or - failing that - GBP
  var $translatable=false; // true=pull value from 'translations' table when $_SESSION['language'] is set
  var $enableShuffle=false;
  var $validate=false; // Switches on/off jQuery and back-end field validation - e.g. for correctly formatted e-mail addresses, numbers etc.
  var $validation="none"; //String for form validation.
  var $error=false; // Validation error gets written here (if the field fails submission validation)
	var $defaultLanguage;
  var $ready;
  var $rawCol=false; // the database definition record of this column (if it has one)
  var $sectionID="default";
  var $extraElements=false;
  var $allowRogueOptions=false;
  public static $uploadDir="./img/uploads/"; // Should be overridden by value in Form
  var $deleteConfirm="Are you sure?";
  var $textAlign = false;
  var $onChange = false;
  var $onDisplay; // Bespokable function for computed columns
  var $style = false;

  var $googleFieldsThatRuinEverything="username,dob";
  var $DB;

	// ----------------
	// STATIC FUNCTIONS
	// ----------------

  public static function computeHtmlName($prefix,$isSubForm,$formName,$name) {
    // Note, we use _s rather than .s here because some browsers get confused by field names with dots in them
    return $prefix.(($isSubForm)?$formName."_":"").$name;
  }
  public static function computeFullName($prefix,$isSubForm,$formName,$name) {
    // Note, we use _s rather than .s here because some browsers get confused by field names with dots in them
    return $prefix.(($isSubForm)?$formName.".":"").$name;
  }
  public static function getUploadDir() { return self::$uploadDir; }

	// -----------
	// CONSTRUCTOR
	// -----------
  // $name,$title,$coltype,$extra="",$default=false,$js=false,$maxChars=false,$require=false,$validation="none",$rawCol=false) {
	function __construct($DB, $id, $name, $v) {
    $this->DB = $DB;
    $this->id=$id;
    $this->name=$name;
    $this->set($v);
  }

  function getSizeLimit() {
    if (!$this->sizeLimit) {
      $this->sizeLimit=min(toBytes(ini_get('upload_max_filesize')),toBytes(ini_get('post_max_size')));
    }
    return $this->sizeLimit;
  }

  // Convert an 'options' into a set of options for use in a SELECT/RADIO/CHECKBOX
	//   a.  [1=>"Banana",2=>"Apple"]  // LEFT AS IS!
	//   b.  "SELECT fruitID,name FROM fruits"
	//   c.  "Banana,Apple"
	function getOptions($options,$existingOptions=false,$sort=0) {
    if (!is_array($options)) {
      if (strpos(strToUpper($options),"SELECT")!==false) {
        // Use the SQL passed in to generate an array of options
        $options = $this->DB->GetArr($options);
      }
    }
    return getOptions($options,$existingOptions,$sort);
  }

  function getSelect($params) {
    if (isset($params['options'])) $params['options'] = $this->getOptions($params['options']);
    return getSelect($params);
  }

	// Col->set([options])
  // Called by AutoForm constructor twice - first to add column from DD, then to override this column's definition with $v being an array of vars to change
  function set($v=false) {
    if (isset($v['rawCol'])) $this->rawCol = $v['rawCol']; // The row from the DD, set if this col was created dynamically by Form
    $coltype=$this->chooseColType($v);
    $coltypeHasChanged=(!(isset($this->coltype)) || (isset($this->coltype) && $this->coltype!=$coltype));
    $this->coltype=$coltype;
    // Set all internal object variables if they were passed as anything other than false
    if ($v) { foreach ($v as $var=>$val) { $this->$var=$val; } }
    // default does not set using this->$var= (perhaps because it is a reserved word?) so do this 'manually'
    if (isset($v['default'])) $this->default=$v['default'];
    // Kill the title column for specific types
    if (isset($v['title'])) $this->title=$v['title'];
    if (isset($v['allowRogueOptions'])) $this->allowRogueOptions=$v['allowRogueOptions'];
    // Some variables require function calls to fully initialise
    if (isset($v['readOnly']) && $v['readOnly']) {
      $this->readOnly();
    }
    if (isset($v['disable']) && $v['disable']) $this->disable();
    if (isset($v['disabled']) && $v['disabled']) $this->disable();
    if (isset($v['process']) && $v['process']) $this->process=true; // Do this _after_ things that switch processing off
    if (isset($v['ignore']) && $v['ignore']) $this->ignore(); // Also sets process=false
    if (isset($v['ignored']) && $v['ignored']) $this->ignore(); // Also sets process=false
    if (isset($v['display']) && !$v['display']) $this->doNotDisplay(); // Also sets process=false
    if (isset($v['val'])) $this->setVal($v['val']);
    if (isset($v['required'])) $v['require']=$v['required']; // Alternative spelling
    if (isset($v['allowHtml'])) $this->allowHtml=$v['allowHtml']; // Alternative spelling
    if (isset($v['size'])) $this->size = $v['size'];
    if (isset($v['resizeWidth'])) {
      if ($this->coltype=='file') $this->coltype='image';
      $this->resizeWidth = $v['resizeWidth'];
    }
    if (isset($v['textAlign'])) $this->textAlign = $v['textAlign'];
    if (isset($v['disableAutoComplete'])) $this->disableAutoComplete = $v['disableAutoComplete'];
    if (isset($v['onDisplay'])) $this->onDisplay = $v['onDisplay'];
    // Determine whether this coltype will be formatted as a string or number
    // $this->formatAsString=$this->formatAsString || (in($coltype,'select,radio,checkbox') && $ops && isset($ops[0]) && !isnum($ops[0])) || in($coltype,'text,textarea,image,file,hiddentext,selecttext,selectoverride,email,password,colour,color,date,datetime');

    // Special cases - set related variables for specific col types (only if the coltype has changed)
		if ($coltypeHasChanged) {
			switch (strToLower($coltype)) {
			case "priceinpence":
				$this->currency="GBP";
				break;
			case "price":
			case "currency":
				$this->currency=(isset($_SESSION['currency']))?$_SESSION['currency']:"GBP";
				break;
			case "checkbox":
			  if (!$this->options) $this->options=array(1=>"");
			  break;
			default:
			  break;
			}
			// Hide hidden columns
			if (strpos(strToLower($coltype),'hidden')!==false) $this->display=1;
	    if (in($coltype,'submit,html')) $this->process=false;
			// Re-format loaded val for this new colType
			if (isset($this->val)) $this->reFormatVal();
		}
		if (isset($v['val'])) $this->setVal($v['val']);
  }

  function chooseColType($v) {
    $ops=false;
    // Coltype can sometimes be inferred by inspecting possible options
    if (isset($v['options'])) $ops=$this->getOptions($v['options']);
    // Choose the coltype
    if (isset($v['coltype'])) {
      $coltype=$v['coltype'];
    } else if (isset($v['options']) && $v['options']) { // ignore options=false
      $numOps=safeCount($ops);
      if (!$ops || $numOps==0) { $coltype="html"; $this->extra="<i>- none -</i>"; }
      else if ($numOps==1) { $coltype="checkbox"; }
      else if ($numOps<3) { $coltype="radio"; }
      else if ((isset($v['default']) && !isNum($v['default'])) || !isNum($this->getVal())) { $coltype="selecttext"; }
      else { $coltype="select"; }
    } else if (isset($v['html'])) {
      $coltype="html";
    } else {
      $coltype=$this->coltype;
    }
    return $coltype;
  }

  function redef($v) { $this->set($v); }

  function getHtmlName() {
    if (strToLower($this->coltype)=="submit") return $this->name;
    return Col::computeHtmlName($this->prefix,$this->isSubForm,$this->formName,$this->name);
  }

  function getFullName() {
    if (strToLower($this->coltype)=="submit") return $this->name;
    return Col::computeFullName($this->prefix,$this->isSubForm,$this->formName,$this->name);
  }

  function pickUp() {
    $fullName=$this->getHtmlName();
    if ($this->allowHtml) {
      if ($this->allowHtml==1 || $this->allowHtml===true) {
        return p($fullName,false,true,2);
      } else {
        return p($fullName,false,true,$this->allowHtml);
      }
    }
    // return p("new".$fullName,p($fullName));
    $val = p("new".$fullName,false,true,1);
    if ($val===false || is_null($val)) {
      $val = p($fullName);
    }
    return $val;
  }

	// Pick up value for this column from the POST data. WARNING: sets to null if nothing posted for it (because, in a way null was posted)
	function pickUpVal() {
	  if ($this->display===0) return;
    // Set val to any submitted value
    $pVal=$this->pickUp();
    if ($pVal || $pVal==="0" || $pVal===0) {
      // Val was posted, so it is about to be set, even if to null
      $this->setVal($pVal);
    } else {
      // Neither was posted
      $this->valSet=false;
      $this->val=null;
    }
	}

  function setReady($readyOrNot) { $this->ready=$readyOrNot; /* here I come, you can't hide() */  return $this->ready;}

  function setPrefix($prefix) { $this->prefix=$prefix; }
	// function setTable($table,$titleCol=null,$keyCol=false,$keyVal=false) { $this->table=$table; $this->titleCol=$titleCol; $this->ownerKeyCol=$keyCol; $this->ownerKeyVal=$keyVal; }

	// Returns this->val if set. If not, for CREATE forms or INSERTs return any default, for EDIT forms and UPDATES leave as is
	function getVal() { return ($this->valSet)?$this->val:$this->getDefault(); }
  function setTitleCol($titleCol) { $this->titleCol=$titleCol; }

	function setVal($newVal=null,$alsoSetDefault=false) {
    $this->val=$newVal; $this->valSet=true; $this->reFormatVal(); if ($alsoSetDefault) $this->setDefault($newVal);
  }
	function setDefault($newVal=null) { /* e("Setting default for ".$this->name." to ".$newVal); */ $this->default=$newVal; }
	function getDefault() {
	  // Checkboxes are a special case, as their default can be implicitely set by the datatype of the underlying column
	  if ($this->coltype=='checkbox' && isnull($this->default)) {
      if (!$this->rawCol) throw new Exception("Column ".$this->name." not found");
      if ($this->rawCol && $this->rawCol['COLUMN_TYPE']=='varchar(1)' || $this->rawCol['COLUMN_TYPE']=='char(1)') {
        $this->setDefault('N');
      } else {
        $this->setDefault(0);
      }
    }
    return $this->default;
	}

	// Format the val passed for the coltype
	function reFormatVal() {
	  $fullName=$this->getHtmlName();
		if (isset($this->val)) {
			switch ($this->coltype) {
			case 'price':
			case 'currency':
			  if ($curSign=has($this->val,"£,$,€,&pound;&euro")) $this->setCurrency($curSign);
			  // No break, flow into number formatting
			case 'number':
			case 'int':
			case 'ordering':
			case 'select':
      // this strips varchar type values, so pass a coltype of selecttext for those guys...
			case 'hiddennumber':
			case 'float':
			case 'decimal':
				$s=cleanNum($this->val);
				if ($s!=="") {
					$i=(float)$s;
					$this->val=((int)$i==$i)?(int)$i:$i;
				}
				break;
			case 'date':
        $this->val=convertDate($this->val,"-");
				break;
			case 'datetime':
      case 'hiddendate':
          if (p($fullName)) { // Reformat user posted dates
					$roughDate=p($fullName).((p($fullName."Time"))?" ".sp($fullName."Time"):"");
					$this->val=convertDate($roughDate,"-");
				}
				break;
			case 'selectoverride':
				$this->val=p("new".$this->name,$this->val);
				break;
			default:
				// Leave the val alone
				break;
			}
		}
		if ($this->options && $this->allowRogueOptions && !isset($this->options[$this->val])) {
			$this->options[$this->val]=getIfSet($this->allowRogueOptions,$this->val,$this->val);
		}
	}

	function clearVal($useDefault=false,$keepClear=false) {
	  if ($useDefault) {
	    $this->val=$this->getDefault();
	  } else {
	    $this->val=null;
	  }
	  $this->valSet=false;
	  $this->keepClear=$keepClear;
	}

	function setTitle($newTitle=null) { $this->title=$newTitle; }

	// Stop the column being processed on save/update. Still appears in HTML though
	function doNotProcess() { $this->process=false; }
  // Display the col as a field, but stop it being updated by the user
	function disable() { $this->disabled=true; $this->doNotProcess(); }
	// Display col as boilerplate text
	function readOnly($process=false) { $this->readOnly=true; $this->process=$process; }
	function required() { $this->require=true; }
	// Remove the col from the HTML, do NOT pick it up, but STILL process it
	function doNotDisplay($giveValue=false,$giveDefault=false) { $this->display=0; if ($giveValue!==false) $this->setVal($giveValue); if ($giveDefault) $this->setDefault($giveDefault); }
  // Completely ignore this col - do not display OR process it
  function ignore() { $this->doNotDisplay(); $this->doNotProcess(); }
	function hidden() { return ($this->display==1 || $this->hidden)?true:false; }
	function autoSubmit($autoSubmit=true) { $this->autoSubmit=$autoSubmit; }
	function setTranslatable($bool=true) { $this->translatable=$bool; }
	function setEnableShuffle($bool=true) { $this->enableShuffle=$bool; }
  function getSection() { return $this->sectionID; }
  function setSection($sectionID) { $this->sectionID=$sectionID; }
	function show() { $this->display=2; }
  // Turn the col into an HTML hidden field
	function hide() {
		if (in($this->coltype,'hiddennumber,number,ordering,float,int,select,yyymmdd,price,currency')) {
      $this->coltype='hidden';
    } else if (in($this->coltype,'date,datetime')) {
      $this->coltype = 'hiddendate';
    } else {
      $this->coltype = 'hiddentext';
    }
		$this->display=($this->display>1)?1:$this->display; // Note: if previously set to doNotDisplay leave it completely off page
	}

  // Validate this col - true if OK, false if a validation error (stored in this->error) occurred
	function validate() {
	  $this->error=false;
		switch ($this->coltype) {
		case 'file':
		case 'image':
			if (!is_uploaded_file($_FILES[$this->name]['tmp_name'])) $this->error="Please browse your computer for ".strtolower($this->title)." file";
			break;
		case 'date':
		case 'datetime':
			if (!$this->valSet) $this->error="Please enter a valid ".str_replace(":"," ",$this->title);
			break;
		/*
		case 'yyyymmdd':
			if ($this->val==0) return "<li>Please enter ".(preg_match("/[aeiou]/i",substr($this->title,0,1))>0?"an":"a")." ".strtolower($this->title)."</li>";
			break;
		case 'dd/mm/yyyy':
			if ($this->val==-1) return "<li>Please enter a valid date for ".strtolower($this->title)."</li>"; else if ($this->val==0) return "<li>Please enter a date for ".strtolower($this->title)."</li>";
			break;
		*/
		case 'email':
		  $msg=validateEmail($this->val);
		  if ($msg!='OK') $this->error="Email ".$msg;
		  break;
		default:
			if (strlen("".$this->val)==0) $this->error="Please enter ".(preg_match("/[aeiou]/i",substr($this->title,0,1))>0?"an":"a")." ".str_replace(":"," ",$this->title);
			break;
		}
		return ($this->error===false);
  }

  // Deprecated. Return the var requested (e.g. $this->[var])
  // function getExtraVar($var) { if (isset($this->$var)) return $this->$var; return false; }

	function setCurrency($inCurrency) { $this->currency=$inCurrency; }

  function getColOptions() {
    // Checkboxes work the same as radio buttons, drop lists etc - using what is passed in the "options" or deprecated "extra" field to determine what is available to select
    // However, if no options are passed and the data field is only one char we assume this is a Y/N tickbox.
    // Similarly, int(1)s are assumed to be boolean 0/1 (false/true) tickboxes
    if ($this->coltype!='checkbox' || $this->options || !$this->rawCol) {
      $existingOptions=false;
      if ($this->allowRogueOptions) {
        $val=$this->getVal();
        if ($val && !isset($inOptions,$val)) $existingOptions=[$val=>$val];
        // e("allowing rogue options val=".$val);
      }
      return $this->getOptions($this->options,$existingOptions);
    }
    if ($this->rawCol['COLUMN_TYPE']=='varchar(1)' || $this->rawCol['COLUMN_TYPE']=='char(1)') {
      return array("Y"=>"");
    } else if ($this->rawCol['COLUMN_TYPE']=='int(1)') {
      return array(1=>"");
    }
    return [];
  }

  function getForSQL($escapeStr=false) {
  	// Use a default?
		if (isnull($this->val) && $this->val!="0") {
		  $default=$this->getDefault();
		  if (isnull($default) && $default!==0) return null; // PHP null value is required by PDO for NULL updates
		  $this->val=$default; // Revert to default
		}
		$val=$this->val;
		if (!$this->formatAsString) return ($val==="NaN")?"NULL":$val;
		// Check size...
		if ($this->maxChars>0 && strlen($val)>$this->maxChars) $val=substr($val,0,$this->maxChars);
		return ($escapeStr)?fss($val):$val;
  }

	// Format val for display as part of a list (or any other read-only situation)
  function getForList($val = false) {
    global $DB;
    $out = $this->getDisplay($val, true);
    // Bespoke code to run?
    if ($this->onDisplay) {
      $func = $this->onDisplay;
      $out = $func($this, $out);
    }
    return $out;
  }

  function getDisplay($val = false, $listView = false) {
    if ($val) $this->setVal($val);
    // if (!$this->isSubForm) trace($this->table." ".$this->name." ".$this->coltype." val = ".$this->val);
    $val=$this->getVal();
    if ($listView && !$val) return "";
    $out="";
    switch ($this->coltype) {
    case 'select':
    case 'selecttext':
    case 'radio':
    case 'checkbox':
      $found=false;
      $colOptions=$this->getColOptions();
      if ($colOptions) {
        foreach ($colOptions as $opVal=>$disp) {
        	if ($val=="") { // don't match null with "0"
        		if ($opVal==="") { $out=$disp; $found=true; }
        	} else if ($val==$opVal) {
        		$out=(isnull($disp))?(($out=='Y' || $out==1)?"√":$opVal):$disp; $found=true;
        	}
        }
      }
      if (!$found) $out=$val;
      break;
    case 'image':
      $ext=substr(strrchr($val,"."),1);
      if (in($ext,$this->validImageExtensions)) $out.="<img src='".$this->getFullUrl($val)."' width='48' height='48' />";
      break;
    case 'multiimage':
    case 'multifile':
      if ($tableInfo=$this->getMultiFileTable()) {
        $br="";
        foreach ($this->DB->GetAll("SELECT * FROM ".$tableInfo['table']." WHERE ".$this->ownerKeyCol."=".$this->ownerKeyVal) as $f) {
          $out.=$br.$f[$tableInfo['col']]; $br="<br />";
        }
      }
      break;
    case 'date':
    case 'datetime': $out.=convertDate($val,"/"); break;
    // case 'yyyymmdd': $out.=convertDate($val,"/","K"); break;
    case 'price': $out.=getCurrencySymbol($this->currency).formatMoney($val); break;
    case 'currency': $out.=getCurrencySymbol($this->currency).formatMoney($val,true); break;
    case 'priceinpence': $out.=getCurrencySymbol($this->currency).formatMoney(($val/100),true); break;
    case 'submit': break;
    case 'password': $out.="*****"; break;
    case 'function': break;
    default: $out.=$val;
    }
		return $out;
  }

  function getTextAlign() {
    if ($this->textAlign !== false) return $this->textAlign;
    if (in($this->coltype,"checkbox,select,selecttext,radio,colour,submit")) return "center";
    if (in($this->coltype,"number,int,integer,float,decimal,price,currency")) return "right";
    return "left";
  }

	// get constructs an HTML <table> row. Use getVal to retrieve the actual column value
  function get($val=false,$layout='V',$wrapInTable=true, $floatOver=false) {
    if (!$this->display && !$this->process) return "";
		$fullName=$this->getHtmlName();
    $out="";
		if ($val!==false) $this->setVal($val);

		// Create the class text here to avoid having to check on each case for both validation and requiredness.
		$inputClasses="kc".$this->coltype;
    if ($this->require) $inputClasses.=" required";
    if ($this->validation != "none") $inputClasses.=$this->validation;
    $inputClasses.=" kc".$this->name;
    if ($this->class) $inputClasses.=" ".$this->class;
		if ($this->require) $inputClasses.=" required";
    if ($this->highlight) $inputClasses.=" highlight";
    $textAlign = $this->getTextAlign();

    $tdClasses = $this->coltype;
    if ($this->tdClass) $tdClasses .= " ".$this->tdClass;

		// 1st table cell (description)
		if ($layout=='V') {
			$out.= (($wrapInTable) ? "<tr".(($this->hidden())?" style='display:none;'":"")." id='".$fullName."Tr'>" : "");
			// 1st table cell (Description)
      if ($this->title) {
        $out.= (($wrapInTable) ? "<td id='".$fullName."TitleTd' class='".$tdClasses."'>" : "");
        $out.="<label id='".$fullName."Title'>".$this->title.(($this->translatable)?" <i>[".$_SESSION['language']." translation]</i>":"")."</label>" . (($wrapInTable) ? "</td>\n" : "\n");
      }
		}
    // 2nd table cell (input field)
    if ($wrapInTable) {
      if (!$this->title) {
        $out.="<td".(($this->hidden())?" colspan='".(($layout=='H')?"2":"3")."' style='display:none;'":" colspan=2").(($floatOver)?" title='".$floatOver."'":"")." id='".$fullName."Td' class='".$tdClasses."'>&nbsp;&nbsp;";
      } else {
        $out.="<td".(($this->hidden())?" colspan='".(($layout=='H')?"1":"2")."' style='display:none;'":"").(($floatOver)?" title='".$floatOver."'":"")." id='".$fullName."Td' class='".$tdClasses."'>";
      }
    }

		if ($this->readOnly) {
  		$out.=$this->getForList();
		} else {
      $opVal=$this->getVal();
			switch (strToLower($this->coltype)) {
			case 'select':
			case 'selecttext':
			case 'selectoverride':
        if (!$this->js && $this->onChange) {
          $this->js = "onChange=\"".$this->onChange."\"";
        }
				$params=[
          'name'=>$fullName,
          'id'=>$fullName,
          'options'=>$this->options,
          'default'=>$opVal,
          'autoSubmit'=>$this->autoSubmit,
          'allowOverride'=>(($this->coltype=='selectoverride')?true:false),
          'disable'=>$this->disabled,
          'js'=>$this->js,
          'class'=>$inputClasses
        ];
        $out.=$this->getSelect($params);
        // If a field is disabled, the browser won't even bother posting it
        if ($this->disabled && $this->process) {
          // If the programmer has`explicity asked for disabled fields to ALSO be processed we need to include a hidden field
          $out.="<input type='hidden' id='".$fullName."' name='".$fullName."' value='".$opVal."' />";
        }
				break;
			case 'radio':
			case 'checkbox':
				$options=$this->getColOptions();
				// For multiple checkbox values use the name "name[]" otherwise PHP won't process multiple items on submit
				$useName=($this->coltype=='checkbox' && safeCount($options)>1)?$fullName."[]":$fullName;
				$val=$this->getVal();
				$multiple=(strpos($val,",")!==false);
				$divID=0;
				$cbClass=($this->coltype=='checkbox' && safeCount($options)==1 && (isset($options[1]) || isset($options['Y'])))?"make-switch switch-small":"CB";
				if ($options) {
					foreach ($options as $opVal=>$disp) {
						$checked=(in($opVal,$val));
						if ($this->coltype=='checkbox') {
						  $id=str_replace('[]','',$useName); // Kill the array shizzle for the id
						  if (safeCount($options)>1) {
  						  $id.=cleanString($opVal,['-']);
  						}
						} else {
						  $id=$useName.cleanString($opVal,[]);
						}
						$fld="<div class='".$cbClass."'>";
            $fld.="<input type='".$this->coltype."' class='".$inputClasses."' id='".$id."' name='".$useName."' value='".$opVal."'".(($checked)?" checked":"").(($this->disabled)?" disabled":"").(($this->js)?" ".$this->js:"")." /> <label for='".$id."'>".$disp."</label>";
            // If a field is disabled, the browser won't even bother posting it
            if ($this->disabled && $this->process) {
              // If the programmer has`explicity asked for disabled fields to ALSO be processed we need to include a hidden field
              $fld.="<input type='hidden' id='".$id."' name='".$name."' value='".$opVal."' />";
            }
            $fld.="</div>";
						if ($this->enableShuffle) {
							$out.="<div class='chkBoxDivSwap'><span id='chkBoxDiv".$fullName.(++$divID)."'>".$fld."</span>";
							if ($divID>1) $out.="<a href='#' onClick='divSwap(\"chkBoxDiv".$fullName.$divID."\",\"chkBoxDiv".$fullName.($divID-1)."\"); return false;'><img src='/i/swap.png' /></a>"; // Need a back curling arrow
							$out.="</div>";
						} else {
							$out.="".$fld;
						}
					}
				}
				break;
			case 'image':
			case 'file':
				if ($this->getVal()) $out.="<input type='radio' id='name='".$fullName."Pref' name='".$fullName."Pref' value='replace'".(($this->disabled)?" disabled":"")." /> Replace with: ";
				$out.="<input type='file' class='".(($this->require)?"required":"")."' id='".$fullName."' name='".$fullName."' size='".$this->size."'".(($this->disabled)?" disabled":"")." /><br />";
				if ($this->getVal()) {
					$out.="<input type='radio' id='".$fullName."Pref' name='".$fullName."Pref' value='clear'".(($this->disabled)?" disabled":"")." /> Clear<br />";
					$out.="<input type='radio' id='".$fullName."Pref' name='".$fullName."Pref' value='keep' checked".(($this->disabled)?" disabled":"")." /> Keep <strong>".$this->getVal()."</strong><br />";
				}
				// if ($this->coltype=="image") { $out.="<img src='".$this->getFullUrl($this->getVal())."' /><br />"; }
				break;
			case 'multifile':
			case 'multiimage':
			  $out.="<div id='".$this->name."multifile'></div>";
        $out.="
          <script>
            var ".$this->name."uploader = new qq.FileUploader({ element: document.getElementById('".$this->name."multifile'), action: '".$_SERVER['PHP_SELF']."', params: {'a':'jsUpload','col':'".$fullName."','".$this->ownerKeyCol."':'".$this->ownerKeyVal."'}, debug: true, showThumbs: true, showFilenames: false });
        ";
        $tableInfo=$this->getMultiFileTable();
        foreach ($this->DB->GetAll("SELECT ".$tableInfo['keyCol'].",".$tableInfo['col']." FROM ".$tableInfo['table']." WHERE ".((nvl($this->ownerKeyVal,0)>0)?$this->ownerKeyCol."=".$this->ownerKeyVal:"sessionID=".fss(session_id()))) as $img) {
          // $url=(file_exists($this->getFullUrl("th_".$img[1])))?$this->getFullUrl("th_".$img[1]):$this->getFullUrl($img[1]);
          $out.=$this->name."uploader.addToInitialList(".$img[0].",'".$img[1]."','".$img[1]."','".$this->getUploadDir()."');";
        }
        $out.="
          </script>
        ";
			  break;
			case 'textarea':
				$columns=$this->cols;
				$rows=$this->rows;
				if($rows == 0) $rows=false;
				if($columns == 0) $columns=false;
				$plainText=($this->plainText || $this->noTinyMce || (isset($_SESSION['richTextToggle']) && $_SESSION['richTextToggle']));
				$columns=($columns)?"cols=".$columns:"";
				$rows=($rows)?"rows=".$rows:"";
				$out.="<textarea id='".$fullName."' name='".$fullName."' ".$columns." ".$rows." ".(($this->disabled)?" disabled":"")." class='".(($plainText)?"plainText":"richText").(($this->require)?" required":"")."'".(($this->extraElements)?" ".$this->extraElements:"").">".$this->getVal()."</textarea>";
				break;
      case 'colour':
      case 'color':
        $value = escHTML($this->getVal());
        // Add in #
        if ($value && strpos($value,'#')===false) $value = '#'.$value;
				$out.="<input id='".$fullName."' name='".$fullName."' type='color' value='".$value."' size='".$this->size."'".(($this->disabled)?" disabled":"")." class='color'".(($this->require)?" required":"")." />";
				break;
			case 'date':
			case 'datetime':
			// case 'yyyymmdd':
        $date = $this->getVal();
				$niceDateTime=convertDate($date,"/");
				$niceDate=justDate($niceDateTime);
				$inputClasses=$inputClasses.=" date";
				/*if (in($fullName,$this->googleFieldsThatRuinEverything)) {
				  $fullName="soc".$fullName;
				}
        */
				$out.="<input type='date' name='".$fullName."' id='".$fullName."' value='".$date."' size='".$this->size."'".(($this->disabled)?" disabled":"")." class='".$inputClasses."'".(($this->js)?" ".$this->js:"").(($this->disableAutoComplete)?" autocomplete='off'":"")." />";
/* 				if (!$this->disabled) $out.="<a href='#' onclick='displayDatePicker(\"".$fullName."\",this); return false;'><img src='js/i/calendarIcon.gif' border=0 class='datePickerImg date' /></a>"; */
				if ($this->coltype=='datetime') {
					$niceTime=justTime($niceDateTime);
					$out.=" <input id='".$fullName."Time' name='".$fullName."Time' value='".$niceTime."' size='7'".(($this->disabled)?" disabled":"")." class='".$inputClasses."' />";
				}
				break;
			case 'hiddentext':
			case 'hiddennumber':
			case 'hidden':
				$out.="<input type='hidden' name='".$fullName."' id='".$fullName."' value='".escQuotes($this->getVal())."' />";
				break;
			case 'price':
			case 'currency':
				$out.=getCurrencySymbol($this->currency)."<input id='".$fullName."' name='".$fullName."' type='text' class='currency'".(($this->require)?" required":"")." value='".formatMoney($this->getVal(),(strToLower($this->coltype)=='currency'))."' size='".$this->size."'".(($this->disabled)?" disabled":"").(($this->js)?" ".$this->js:"").(($this->disableAutoComplete)?" autocomplete='off'":"")." class='".$inputClasses."' />";
				break;
			case 'ordering':
				$ord=0;
				$sql ="";
				$out.="<select id='".$fullName."' name='".$fullName."'".(($this->disabled)?" disabled":"")." class='".$inputClasses."'>";
				// Use the Constraining Where Clause (if set)
				if ($this->cwc && strpos(strtoupper($this->cwc), "SELECT") > -1){
					$sql=$this->cwc;
				} else {
					$sql=where("SELECT * FROM ".$this->table." ORDER BY ordering",$this->cwc);
				}
				$orderRows=$this->DB->GetAll($sql);
				$n=0;
				$gotVal=false;
				foreach ($orderRows as $r1) {
					$n++;
					$ord=$r1['ordering'];
					// Get a nice description of what is currently at each ordering, if at all possible
					$title=(isset($this->titleCol) && isset($r1[$this->titleCol]))?$r1[$this->titleCol]:"position ".$n;
					if ($ord==$this->getVal()) {
					  $gotVal=true;
						$out.="<option value='".$ord."' selected>".(($ord==1)?"At top":"-> ".$ord)."</option>";
					} else {
						$out.="<option value='".$ord."'>(".((isnull($this->val) || $this->val>$ord)?"before":"after")." ".$title.")</option>";
					}
				}
				if (!$gotVal) $out.="<option value='".($ord+1)."' selected>At bottom</option>";
				$out.="</select>";
				break;
			case 'submit':
				foreach(explode(",",$this->extra) as $btn) {
					if ($btn=="Delete") {
						$out.="<input id='".escVar($btn." Btn")."' name='".$fullName."' class='btn' type='submit' value='".$btn."' onClick='if ($(\"#serialized\", this.form).val()==1) { $(\"#submitBtn\", this.form).val($(this).val()); } return confirm(\"".$this->deleteConfirm."\");'".(($this->disabled)?" disabled":"")." />";
					} else {
						$out.="<input id='".escVar($btn." Btn")."' name='".$fullName."' class='btn' type='submit' value='".$btn."'".(($this->disabled)?" disabled":"")." onClick='if ($(\"#serialized\", this.form).val()==1) { $(\"#submitBtn\", this.form).val($(this).val()); } window.onbeforeunload=null;' />";
					}
				}
				break;
		  case 'number': case 'float': case 'decimal':
		  	$inputClasses.=" number";
				$out.='<input id="'.$fullName.'" name="'.$fullName.'" style="text-align:right;" type="text" value="'.escDoubleQuotes($this->getVal()).'" size="'.$this->size.'"'.(($this->disabled)?' disabled':'').(($this->js)?" ".$this->js:"").(($this->disableAutoComplete)?" autocomplete='off'":"").' onBlur="this.value=this.value.replace(/[^0-9\.\-]/g,\'\');" class="'.$inputClasses.'" />';
				break;
		  case 'integer': case 'int':
		  	$inputClasses.=" integer";
				$out.='<input id="'.$fullName.'" name="'.$fullName.'" style="text-align:right;" type="text" value="'.escDoubleQuotes($this->getVal()).'" size="'.$this->size.'"'.(($this->disabled)?' disabled':'').(($this->js)?" ".$this->js:"").(($this->disableAutoComplete)?" autocomplete='off'":"").' onBlur="this.value=this.value.replace(/[^0-9]/g,\'\');" class="'.$inputClasses.'" />';
				break;
			case 'html':
				$out.=$this->html;
				break;
			default:
				// 'text' or 'password'
				$out.='<input id="'.$fullName.'" name="'.$fullName.'" type="'.$this->coltype.'" class="'.$inputClasses.'" value="'.escDoubleQuotes($this->getVal()).'" size="'.$this->size.'"'.(($this->disabled)?' disabled':'').(($this->js)?" ".$this->js:"").(($this->disableAutoComplete)?" autocomplete='off'":"").' />';
				break;
			}
			// 3rd table cell (validation error - if there are any)
			if ($this->error) {
  			$out.="<span class='error'>".$this->error."</span>";
  	  }
		} // if readOnly
		if ($wrapInTable) {
      $out.="</td>";
      if ($layout=='V') $out.="</tr>";
    }
		return $out;
	} // get()

	// Equivalent to get for the heading row of a multiForm table
  function getForHeading($showMultiEditOptions=false) {
    if (!$this->display && !$this->process) return "";
		if (!in(strToLower($this->coltype),"select,selecttext,selectoverride,radio,checkbox")) $showMultiEditOptions=false;
		$fullName=$this->getHtmlName();
    $c="";
		$c.="<th".(($this->hidden())?" style='display:none;'":" style='text-align:".$this->getTextAlign()."'")." id='".$fullName."Th'>".$this->title;
		// Include multi-edit dropper?
		if (!$showMultiEditOptions) return $c."</th>\n";
		switch (strToLower($this->coltype)) {
		case 'select':
		case 'selecttext':
		case 'selectoverride':
			$options=$this->getColOptions();
			$js="onChange='var v=$(this).val(); var s=\".kc".$this->name."\"; $(s).val(v);'";
			$params=[
        'name'=>$fullName."Multi",
        'id'=>$fullName."Multi",
        'options'=>addNullOption($options,"MULTIIGNORE","* Multi-choose"),
        'js'=>$js
      ];
      $c.=" ".$this->getSelect($params);
			break;
		case 'radio':
		case 'checkbox':
			$options=$this->getColOptions();
			// For multiple checkbox values use the name "name[]" otherwise PHP won't process multiple items on submit
			$useName=($this->coltype=='checkbox' && safeCount($options)>1)?$fullName."[]":$fullName;
			$val=$this->getVal();
			$multiple=(strpos($val,",")!==false);
			$divID=0;
			$cbClass=($this->coltype=='checkbox' && safeCount($options)==1 && (isset($options[1]) || isset($options['Y'])))?"make-switch switch-small":"CB";
			if (is_array($options)) {
				foreach ($options as $opVal=>$disp) {
					$js="onChange='var v=$(this).prop(\"checked\"); var s=\".kc".$this->name."\"; $(s).prop(\"checked\",v); switchSwitch(s,v)'";
					$c.="<div class='".$cbClass."'><input type='".$this->coltype."' id='".$useName."' name='".$useName."' value='".$opVal."'".$js." /> ".$disp."</div>";
				}
			}
			break;
		default: break;
		}
		$c.="</th>";
		return $c;
	} // getForHeading()

  function toString() {
    $comma="";
    $s="{";
    foreach($this as $var=>$val) {
      echo $comma."<b>".$var."</b>:".sTest($val); $comma=", ";
    }
    $s.="}";
  }

  // FILE HANDLING
  function handleFileDelete() {
    // rm the file in question
    if (notnull($this->val) && file_exists($this->getFullUrl($this->val))) unlink($this->getFullUrl($this->val));
    // rm the thumbnail (if applicable)
    if ($this->coltype=='image' || $this->coltype=='multiimage') {
      if (file_exists($this->getFullUrl("th_".$this->val))) unlink($this->getFullUrl("th_".$this->val));
      if (file_exists($this->getFullUrl("med_".$this->val))) unlink($this->getFullUrl("med_".$this->val));
    }
    return true;
  }

  function getFullUrl($filename) { return $this->getUploadDir().$filename; }

  // Check the ->file information that it's valid and set a couple extra values
  function setupFile($rawFilename,$size) {
    $file=[];
    // Check the size of the file is OK
    if ($size==0) return array('error'=>'File is empty');
    if ($size > $this->getSizeLimit()) return array('error'=>'File is too large');
    // Work out new filename and extension
    if ($this->usePkAsFileName) {
      $file['rawFilename']=$this->ownerKeyVal;
    } else {
      $file['rawFilename']=escFile($rawFilename);
    }
    $file['dir']=$this->getUploadDir();
    $file['ext']=substr(strrchr($file['rawFilename'], "."),1);
    $file['filename']=substr($file['rawFilename'],0,strpos($file['rawFilename'],".".$file['ext']));
    $file['size']=$size;
    // CHECK (albeit loosely) that file is valid
    if ($this->coltype=='image' || $this->coltype=='multiimage') {
      if (!in(strToLower($file['ext']),strToLower($this->validImageExtensions))) {
        throw new Exception("Only ".$this->validImageExtensions." please!");
      }
    } else {
      // Check list of valid extensions, plus allow for edi files (.X00 to .X99)
      if (!in(strToLower($file['ext']),strToLower($this->validFileExtensions)) && substr(strToLower($file['ext']),0,1)!='x' && !intval(substr($file['ext'],1,2))) {
        throw new Exception("Only ".$this->validFileExtensions." please!");
      }
    }
    // Overwrite existing files only if explicitely requested, or when ignoring filename and using PK
    $file['overwrite']=($this->overwrite || $this->usePkAsFileName);
    $file['fullFilename']=$this->getUploadDir().$file['filename'].'.'.$file['ext'];
    if (!$file['overwrite'] && file_exists($file['fullFilename'])) {
      // Ensure this file has a unique name
      $counter=0;
      while (file_exists($file['fullFilename']) && 99>$counter++) {
        $file['fullFilename']=$this->getUploadDir().$file['filename'].$counter.'.'.$file['ext'];
      }
      if ($counter>0) $file['filename']=$file['filename'].$counter;
    }
    return $file;
	}

  // Handle AJAX requests from a multifile or multiimage coltype
  function handleJsUpload() {
    // Determine how the file is being transferred (streamed via XMLHttpRequest or posted via $_FILES)
    if (isset($_GET['qqfile'])) {
        $this->uploadMethod="XHR";
        $rawFilename=$_GET['qqfile'];
        if (isset($_SERVER["CONTENT_LENGTH"])) {
          $size=(int)$_SERVER["CONTENT_LENGTH"];
        } else {
          return array('error'=>'Getting content length is not supported.');
        }
    } elseif (isset($_FILES['qqfile'])) {
        $this->uploadMethod="FILES";
        $rawFilename=$_FILES['qqfile']['name'];
        $size=$_FILES['qqfile']['size'];
    } else {
        return array('error' => 'No files were uploaded.');
    }
    // Check file size, extension, etc...
    $file=$this->setupFile($rawFilename,$size);
    if (isset($file['error'])) return $res;
    if ($this->uploadMethod=='XHR') {
      // Stream the file in...
      $input = fopen("php://input", "r");
      $temp = tmpfile();
      $realSize = stream_copy_to_stream($input, $temp);
      fclose($input);
      if ($realSize != $file['size']) return false;
      $target=fopen($file['fullFilename'],"w");
      fseek($temp, 0, SEEK_SET);
      stream_copy_to_stream($temp, $target);
      fclose($target);
    } else {
      // Move the uploaded file to it's proper location
      if (!move_uploaded_file($_FILES['qqfile']['tmp_name'], $file['fullFilename'])) return array('error'=>"AJAX file qqfile was uploaded, but could not be moved to ".$file['fullFilename']);
    }
    $this->processUpload($file);
    // Save the info into the join table (this col itself is a pseudo-virtual column that does not get written to the DB)
    if ($tableInfo=$this->getMultiFileTable()) {
      $this->DB->execute("INSERT INTO ".$tableInfo['table']." (".$tableInfo['col'].",".$this->ownerKeyCol.",sessionID) VALUES (".fss($file['filename'].".".$file['ext']).",".np($this->ownerKeyCol,0).",".fss(session_id()).")");
      $id=$this->DB->GetOne("SELECT MAX(".$tableInfo['keyCol'].") FROM ".$tableInfo['table']." WHERE sessionID=".fss(session_id()));
    }
    return array('success'=>true,'id'=>$id);
  }

  // Returns the table info which a multifile or multiimage col will use to store images, or false if a valid table cannot be found
  function getMultiFileTable() {
    $res=[];
    $res['table']=nvl($this->imageTable,$this->fileTable,$this->table,$this->name);
    if ($res['table'] && $cols=$this->DB->getColsFromDD($res['table'])) {
      $validCols=iExplode($this->DB->keyCollapse($cols,"COLUMN_NAME"));
      $res['col']=nvl($this->imageCol,$this->fileCol,$this->name,"imageUrl");
      $res['keyCol']=$this->DB->getPkFromDD($res['table']);
      if (isset($validCols[$res['col']]) && isset($validCols[$this->ownerKeyCol]) && isset($validCols['sessionID'])) {
        return $res;
      }
      echo "<p>WARNING: MultiFile cannot function :( One of these is missing on ".$res['table'].": ".$res['col']." or ".$this->ownerKeyCol." or sessionID</p>";
    } else {
      echo "<p>WARNING: MultiFile cannot function :( table [".$res['table']."] has no columns!</p>";
    }
    return false;
  }

	// Upload a picture or file, setting it's col-val to the new filename
	function handleUpload() {
    $preColName=$this->getHtmlName();
    // avoiding arrays of ['name'] is so that html5 multi-file uploads are not handled here - these are handled in asset.php
    if (isset($_FILES[$preColName]) && !is_array($_FILES[$preColName]['name']) && is_uploaded_file($_FILES[$preColName]['tmp_name'])) {
      $size=$_FILES[$preColName]['size'];
      $rawFilename=$_FILES[$preColName]['name'];
      // Check file size, extension, etc...
      $file=$this->setupFile($rawFilename,$size);
      move_uploaded_file($_FILES[$preColName]['tmp_name'], $_SERVER['DOCUMENT_ROOT'].$file['fullFilename']);
      $this->setVal($file['filename'].".".$file['ext']);
      // Create thumbnails etc (if applicable)
			$this->processUpload($file);
			return true;
		}
		return false;
	}

  function processUpload($file) {
    // Set permissions to read and write for all (but NOT execute)
    chmod($_SERVER['DOCUMENT_ROOT'].$file['fullFilename'],0666);
    if ($this->coltype=='image' || $this->coltype=='multiimage') {
      // Thumbnail first (if required) to get best quality from original image
      if ($this->thumbWidth || $this->thumbHeight || $this->thumbMethod) {
        $thumbWidth=nvl($this->thumbWidth,0);
        $thumbHeight=nvl($this->thumbHeight,0);
        $resizeMethod=nvl($this->thumbMethod,$this->resizeMethod);
        resizeImage($file['fullFilename'], $thumbWidth, $thumbHeight, $file['dir']."th_".$file['filename'].".".$file['ext'], $this->noResizeIfOriginalSmaller, 100, $resizeMethod);
      }
      // Resize main image?
      if ($this->resizeWidth || $this->resizeHeight || $this->resizeMethod) {
        $width=nvl($this->resizeWidth,0);
        $height=nvl($this->resizeHeight,0);
        $resizeMethod=nvl($this->resizeMethod,"STRETCH");
      } else {
        list($width, $height) = getimagesize($file['fullFilename']);
        $resizeMethod="NONE";
      }
      resizeImage($file['fullFilename'],$width,$height,null,$this->noResizeIfOriginalSmaller,100,$resizeMethod);
    }
    return true;
  }

  function highlight() {
    $this->highlight=1;
  }

  // Set this cell's style; reset after use
  function setStyle($s) {
    if ($s) $this->style = $s;
  }

} // class Col

class JoinCol {

  var $type='JoinCol';
  var $display=2; // Used when displaying the form (e.g. through getForm()) : 0=Not in HTML at all, 1=Hidden, 2=Displayed
  var $process=true; // false=Do-not-process, true=Process
  var $disabled=false; // true=do not update (but will display the HTML field as disabled)
  var $readOnly=false; // true=do not update (but will display as boiler plate text)
  var $table; // Table 1 - the DB table handed down from parent Form
  var $prefix="";
  var $formName="";
  var $isSubForm=false;
  var $name;
  var $title="";
  var $ownerKeyCol=false;
  var $ownerKeyVal=false;
  var $joinTable; // Table 2 - the join table
  var $joinedTable; // Table 3 - the joined-to table
  var $joinedTableKeyCol;
  var $joinedTableSQL;
  var $disableAutoComplete=0;
  var $DB;

  function __construct($DB, $name,$title,$joinTable,$joinedTable,$joinedTableSQL) {
    $DB = $this->DB;
    $this->name=$name;
    $this->title=$title;
    $this->joinTable=$joinTable;
    $this->joinedTable=$joinedTable;
    $this->joinedTableSQL=$joinedTableSQL;
    $this->joinedTableKeyCol=$this->DB->getPKfromDD($this->joinedTable);
  }

	function hidden() { return ($this->display==1)?true:false; }
  function getHtmlName() { return Col::computeHtmlName($this->prefix,$this->isSubForm,$this->formName,$this->name); }
  function setPrefix($prefix) { $this->prefix=$prefix; }
  function setTitleCol($titleCol) { $this->titleCol=$titleCol; }

  function get($val=false,$layout='V',$wrapInTable=true) {
    $c="\n";
    if (!$this->display && !$this->process) return "";
		$fullName=$this->getHtmlName();
    $c="";
		if ($wrapInTable) {
      if ($layout=='V') $c.="<tr>";
		  $c.="
		      <td".(($this->hidden())?" style='display:none;'":"")." valign='top'><i>".$this->title."</i></td>
		      <td>
		  ";
    }
    $alreadyChecked=$this->DB->GetArr("SELECT ".$this->joinedTableKeyCol.",".$this->ownerKeyCol." FROM ".$this->joinTable." WHERE ".$this->ownerKeyCol."=".nvl($this->ownerKeyVal,0));
    $items=[];
    foreach ($this->DB->GetArr($this->joinedTableSQL) as $keyVal=>$desc) {
      $checked=(isset($alreadyChecked[$keyVal]));
      array_push($items,"<input type='checkbox' name='".$this->getHtmlName()."[]' value='".$keyVal."' ".(($checked)?" checked":"")." /> ".$desc);
    }
    $c.=tabulate($items,2);
		if ($wrapInTable) {
      $c.="</td>";
      if ($layout=='V') $c.="</tr>";
    }
		return $c;
  }

  // Handle saving of posted joinCol info
  function save() {
    $wasChecked=$this->DB->GetArr("SELECT ".$this->joinedTableKeyCol.",".$this->ownerKeyCol." FROM ".$this->joinTable." WHERE ".$this->ownerKeyCol."=".nvl($this->ownerKeyVal,0));
    $nowChecked=iExplode(sp($this->getHtmlName()));
    $items=[];
    // Only process those records valid in the given SQL
    foreach ($this->DB->GetArr($this->joinedTableSQL) as $joinedTableKeyVal=>$desc) {
      // Update the join-table
      if (isset($wasChecked[$joinedTableKeyVal])===isset($nowChecked[$joinedTableKeyVal])) {
        // Nothing to do - either stayed checked, or stayed unchecked
      } else if (isset($wasChecked[$joinedTableKeyVal])) {
        $this->DB->execute("DELETE FROM ".$this->joinTable." WHERE ".$this->ownerKeyCol."=".$this->ownerKeyVal." AND ".$this->joinedTableKeyCol."=".$joinedTableKeyVal);
      } else if (isset($nowChecked[$joinedTableKeyVal])) {
        $this->DB->execute("INSERT INTO ".$this->joinTable."(".$this->ownerKeyCol.",".$this->joinedTableKeyCol.") VALUES (".$this->ownerKeyVal.",".$joinedTableKeyVal.")");
      }
    }
  }
} // class JoinCol
