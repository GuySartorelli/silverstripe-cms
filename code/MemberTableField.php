<?php
/**
 * Enhances {ComplexTableField} with the ability to list groups and given members.
 * It is based around groups, so it deletes Members from a Group rather than from the entire system.
 *
 * In contrast to the original implementation, the URL-parameters "ParentClass" and "ParentID" are used
 * to specify "Group" (hardcoded) and the GroupID-relation.
 *
 *	@todo write a better description about what this field does.
 *
 * Returns either:
 * - provided members
 * - members of a provided group
 * - all members
 * - members based on a search-query
 * @package cms
 * @subpackage security
 */
class MemberTableField extends ComplexTableField {
	
	protected $members;
	
	protected $hidePassword;
	
	protected $detailFormValidator;
	
	protected $group;

	protected $template = 'MemberTableField';

	public $popupClass = 'MemberTableField_Popup';
	
	static $data_class = 'Member';
	
	/**
	 * Set the page size for this table. 
	 * @var int 
	 */ 
	public static $page_size = 20; 
 	
	/**
	 * @deprecated 2.4. See {@link MemberTableField->addPermissions()}
	 */
	private static $addedPermissions = array();
	
	/**
	 * @deprecated 2.4: See {@link MemberTableField->addMembershipFields()}
	 */
	private static $addedFields = array();

	/**
	 * @deprecated 2.4: See {@link MemberTableField->addMembershipFields()}
	 */
	private static $addedCsvFields = array();

	/**
	 * @deprecated 2.4. Set permissions using setPermissions(Array) on
	 * the MemberTableField object.
	 */
	public static function addPermissions($addingPermissionList) {
		trigger_error('MemberTableField::addPermissions() is deprecated. Please set permissions using setPermissions(Array) on the MemberTableField object.', E_USER_NOTICE);
		self::$addedPermissions = $addingPermissionList;
	}
	
	/**
	 * @deprecated 2.4: Please use a DataObjectDecorator, implementing updateSummaryFields
	 * to alter the table overview fields instead.
	 */
	public static function addMembershipFields($addingFieldList, $addingCsvFieldList = null) {
		trigger_error('MemberTableField::addMembershipFields() is deprecated. Please implement updateSummaryFields() on a Member decorator instead.', E_USER_NOTICE);
		self::$addedFields = $addingFieldList;
		$addingCsvFieldList == null ? self::$addedCsvFields = $addingFieldList : self::$addedCsvFields = $addingCsvFieldList;
  	}

  	/**
  	 * Constructor method for MemberTableField.
  	 * 
  	 * @param Controller $controller Controller class which created this field
  	 * @param string $name Name of the field (e.g. "Members")
  	 * @param mixed $group Can be the ID of a Group instance, or a Group instance itself
  	 * @param DataObjectSet $members Optional set of Members to set as the source items for this field
  	 * @param boolean $hidePassword Hide the password field or not in the summary?
  	 */
	function __construct($controller, $name, $group, $members = null, $hidePassword = true) {
		$sourceClass = self::$data_class;
		$SNG_member = singleton($sourceClass);
		$fieldList = $SNG_member->summaryFields();
		$memberDbFields = $SNG_member->db();
		$csvFieldList = array();

		foreach($memberDbFields as $field => $dbFieldType) {
			$csvFieldList[$field] = $field;
		}
		
		if($group) {
			if(is_object($group)) {
				$this->group = $group;
			} elseif(is_numeric($group)) {
				$this->group = DataObject::get_by_id('Group', $group);
			}
		} else if(isset($_REQUEST['ctf']) && is_numeric($_REQUEST['ctf'][$this->Name()]["ID"])) {
			$this->group = DataObject::get_by_id('Group', $_REQUEST['ctf'][$this->Name()]["ID"]);
		}

		foreach(self::$addedFields as $key => $value) {
			$fieldList[$key] = $value;
		}

		if(!$hidePassword) {
			$fieldList["SetPassword"] = "Password"; 
		}

		$this->hidePassword = $hidePassword;
		
		// @todo shouldn't this use $this->group? It's unclear exactly
		// what group it should be customising the custom Member set with.
		if($members) {
			$this->setCustomSourceItems($this->memberListWithGroupID($members, $group));
		}

		parent::__construct($controller, $name, $sourceClass, $fieldList);
		
		$SQL_search = isset($_REQUEST['MemberSearch']) ? Convert::raw2sql($_REQUEST['MemberSearch']) : null;
		if(!empty($_REQUEST['MemberSearch'])) {
			$searchFilters = array();
			foreach($SNG_member->searchableFields() as $fieldName => $fieldSpec) {
				if(strpos($fieldName, '.') === false) $searchFilters[] = "\"$fieldName\" LIKE '%{$SQL_search}%'";
			}
			$this->sourceFilter[] = '(' . implode(' OR ', $searchFilters) . ')';
		}

		$this->sourceJoin = " INNER JOIN \"Group_Members\" ON \"MemberID\"=\"Member\".\"ID\"";
		$this->setFieldListCsv($csvFieldList);
		$this->setPageSize($this->stat('page_size'));
	}
	
	function FieldHolder() {
		$ret = parent::FieldHolder();
		
		Requirements::javascript(CMS_DIR . '/javascript/MemberTableField.js');
		Requirements::javascript(CMS_DIR . "/javascript/MemberTableField_popup.js");
		
		return $ret;
	}

	function sourceID() {
		return ($this->group) ? $this->group->ID : 0;
	}

	function AddLink() {
		return $this->Link() . '/add';
	}

	function SearchForm() {
		$groupID = (isset($this->group)) ? $this->group->ID : 0;
		$query = isset($_GET['MemberSearch']) ? $_GET['MemberSearch'] : null;
		
		$searchFields = new FieldGroup(
			new TextField('MemberSearch', _t('MemberTableField.SEARCH', 'Search'), $query),
			new HiddenField("ctf[ID]", '', $groupID),
			new HiddenField('MemberFieldName', '', $this->name),
			new HiddenField('MemberDontShowPassword', '', $this->hidePassword)
		);

		$actionFields = new LiteralField('MemberFilterButton','<input type="submit" class="action" name="MemberFilterButton" value="'._t('MemberTableField.FILTER', 'Filter').'" id="MemberFilterButton"/>');

		$fieldContainer = new FieldGroup(
			$searchFields,
			$actionFields
		);

		return $fieldContainer->FieldHolder();
	}

	/**
	 * Add existing member to group rather than creating a new member
	 */
	function addtogroup() {
		$data = $_REQUEST;
		unset($data['ID']);
		$ctfID = isset($data['ctf']) ? $data['ctf']['ID'] : null;

		if(!is_numeric($ctfID)) {
			FormResponse::status_messsage(_t('MemberTableField.ADDINGFIELD', 'Adding failed'), 'bad');
		}

		$className = self::$data_class;
		$record = new $className();

		$record->update($data);
		
		$valid = $record->validate();

		if($valid->valid()) {
			$record->write();
			$record->Groups()->add($ctfID);

			$this->sourceItems();

			// TODO add javascript to highlight added row (problem: might not show up due to sorting/filtering)
			FormResponse::update_dom_id($this->id(), $this->renderWith($this->template), true);
			FormResponse::status_message(
				_t(
					'MemberTableField.ADDEDTOGROUP','Added member to group'
				),
				'good'
			);
		
		} else {
			$message = sprintf(
				_t(
					'MemberTableField.ERRORADDINGUSER',
					'There was an error adding the user to the group: %s'
				),
				Convert::raw2xml($valid->starredList())
			);
			
			FormResponse::status_message($message, 'bad');
		}

		return FormResponse::respond();
	}

	/**
	 * Custom delete implementation:
	 * Remove member from group rather than from the database
	 */
	function delete() {
		$groupID = Convert::raw2sql($_REQUEST['ctf']['ID']);
		$memberID = Convert::raw2sql($_REQUEST['ctf']['childID']);
		if(is_numeric($groupID) && is_numeric($memberID)) {
			$member = DataObject::get_by_id('Member', $memberID);
			$member->Groups()->remove($groupID);
		} else {
			user_error("MemberTableField::delete: Bad parameters: Group=$groupID, Member=$memberID", E_USER_ERROR);
		}

		return FormResponse::respond();

	}

	/**
	 * #################################
	 *           Utility Functions
	 * #################################
	 */
	function getParentClass() {
		return 'Group';
	}

	function getParentIdName($childClass, $parentClass) {
		return 'GroupID';
	}

	/**
	 * #################################
	 *           Custom Functions
	 * #################################
	 */
	
	/**
	 * Customise an existing DataObjectSet of Member
	 * objects with a GroupID.
	 * 
	 * @param DataObjectSet $members Set of Member objects to customise
	 * @param Group $group Group object to customise with
	 * @return DataObjectSet Customised set of Member objects
	 */
	function memberListWithGroupID($members, $group) {
		$newMembers = new DataObjectSet();
		foreach($members as $member) {
			$newMembers->push($member->customise(array('GroupID' => $group->ID)));
		}
		return $newMembers;
	}

	function setGroup($group) {
		$this->group = $group;
	}
	
	function GetControllerName() {
		return $this->controller->class;
	}

	/**
	 * Add existing member to group by name (with JS-autocompletion)
	 */
	function AddRecordForm() {
		$fields = new FieldSet();
		foreach($this->FieldList() as $fieldName => $fieldTitle) {
			// If we're adding the set password field, we want to hide the text from any peeping eyes
			if($fieldName == 'SetPassword') {
				$fields->push(new PasswordField($fieldName));
			} else {
				$fields->push(new TextField($fieldName));
			}
		}
		if($this->group) {
			$fields->push(new HiddenField('ctf[ID]', null, $this->group->ID));
		}
		$actions = new FieldSet(
			new FormAction('addtogroup', _t('MemberTableField.ADD','Add'))
		);
		
		return new TabularStyle(
			new NestedForm(
				new Form(
					$this,
					'AddRecordForm',
					$fields,
					$actions
				)
			)
		);
	}

	/**
	 * Same behaviour as parent class, but adds the
	 * member to the passed GroupID.
	 *
	 * @return string
	 */
	function saveComplexTableField($data, $form, $params) {
		$className = $this->sourceClass();
		$childData = new $className();
		$form->saveInto($childData);
		$childData->write();

		$childData->Groups()->add($data['GroupID']);
		
		$closeLink = sprintf(
			'<small><a href="' . $_SERVER['HTTP_REFERER'] . '" onclick="javascript:window.top.GB_hide(); return false;">(%s)</a></small>',
			_t('ComplexTableField.CLOSEPOPUP', 'Close Popup')
		);
		$message = sprintf(
			_t('ComplexTableField.SUCCESSADD', 'Added %s %s %s'),
			$childData->singular_name(),
			'<a href="' . $this->Link() . '">' . $childData->Title . '</a>',
			$closeLink
		);
		$form->sessionMessage($message, 'good');

		Director::redirectBack();		
	}	
	
	/**
	 * Cached version for getting the appropraite members for this particular group.
	 *
	 * This includes getting inherited groups, such as groups under groups.
	 */
	function sourceItems() {
		// Caching.
		if($this->sourceItems) {
			return $this->sourceItems;
		}

		// Setup limits
		$limitClause = '';
		if(isset($_REQUEST['ctf'][$this->Name()]['start']) && is_numeric($_REQUEST['ctf'][$this->Name()]['start'])) {
			$limitClause = ($_REQUEST['ctf'][$this->Name()]['start']) . ", {$this->pageSize}";
		} else {
			$limitClause = "0, {$this->pageSize}";
		}
				
		// We use the group to get the members, as they already have the bulk of the look up functions
		$start = isset($_REQUEST['ctf'][$this->Name()]['start']) ? $_REQUEST['ctf'][$this->Name()]['start'] : 0; 
		
		$this->sourceItems = false;
		if($this->group) {
			$this->sourceItems = $this->group->Members( 
				$this->pageSize, // limit 
				$start, // offset 
				$this->sourceFilter,
				$this->sourceSort
			);	
		}
		// Because we are not used $this->upagedSourceItems any more, and the DataObjectSet is usually the source
		// that a large member set runs out of memory. we disable it here.
		//$this->unpagedSourceItems = $this->group->Members('', '', $this->sourceFilter, $this->sourceSort);
		$this->totalCount = ($this->sourceItems) ? $this->sourceItems->TotalItems() : 0;
		
		return $this->sourceItems;
	}

	function TotalCount() {
		$this->sourceItems(); // Called for its side-effect of setting total count
		return $this->totalCount;
	}

	/**
	 * Handles item requests
	 * MemberTableField needs its own item request class so that it can overload the delete method
	 */
	function handleItem($request) {
		return new MemberTableField_ItemRequest($this, $request->param('ID'));
	}
}

/**
 * Popup window for {@link MemberTableField}.
 * @package cms
 * @subpackage security
 */
class MemberTableField_Popup extends ComplexTableField_Popup {
	
	function forTemplate() {
		$ret = parent::forTemplate();
		
		Requirements::javascript(CMS_DIR . '/javascript/MemberTableField.js');
		Requirements::javascript(CMS_DIR . '/javascript/MemberTableField_popup.js');
		
		return $ret;
	}

}

class MemberTableField_ItemRequest extends ComplexTableField_ItemRequest {
	/**
	 * Deleting an item from a member table field should just remove that member from the group
	 */
	function delete() {
		if($this->ctf->Can('delete') !== true) {
			return false;
		}
		
		$groupID = $this->ctf->sourceID();
		$this->dataObj()->Groups()->remove($groupID);
	}
	
}

?>
