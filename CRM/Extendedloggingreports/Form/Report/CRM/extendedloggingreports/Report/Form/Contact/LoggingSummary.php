<?php


class CRM_Extendedloggingreports_Form_Report_CRM_extendedloggingreports_Report_Form_Contact_LoggingSummary extends CRM_Report_Form_Contact_LoggingSummary {
  protected $_groupByDateFreq = array(
    'DAY_MICROSECOND' => 'Microsecond (Single Transaction)',
    'DAY_MINUTE' => '1 minute',
    'DAY_HOUR' => 'One Hour',
    'DAY' => 'One Day',
    'MONTH' => 'One Month',
  );
  protected $timeInterval = 'DAY_MICROSECOND';
  protected $_groupConcatSeparator = ',';
  protected $_groupFilter = true;
  protected $_tempTable = '';

  function __construct() {
    parent::__construct();

    $this->_columns['log_civicrm_entity']['group_bys'] = array(
      'log_date' => array(
          'title' => ts('When'),
          'operatorType' => CRM_Report_Form::OP_DATE,
          'type' => CRM_Utils_Type::T_TIME,
          'frequency' => TRUE,
      ),
      'altered_contact_id' => array(
          'title' => ts('Altered Contact'),
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'alias' => 'modified_contact_civireport',
      ),
      'log_action' => array(
          'operatorType' => CRM_Report_Form::OP_MULTISELECT,
          'options' => array('Insert' => ts('Insert'), 'Update' => ts('Update'), 'Delete' => ts('Delete')),
          'title' => ts('Action'),
          'type' => CRM_Utils_Type::T_STRING,
          'required' => TRUE,
      ),
      'log_conn_id' => array(
          'title' => ts('Transaction or batch'),
          'type' => CRM_Utils_Type::T_INT,
      ),
      );
    $this->_columns['civicrm_contact']['group_bys'] =
       array(
         'display_name' => array(
                'name' => 'display_name',
                'title' => ts('Altered By'),
                'type' => CRM_Utils_Type::T_STRING,
        ),
      );
    $this->_columns['civicrm_contact']['pseudofields'] = array(
        'dao'   => 'CRM_Contact_DAO_Contact',
        'filters' => array(
          'limit' => array(
            'title' => ts('Number of Records (note that this works best on a single entity type'),
            'type' => CRM_Utils_Type::T_INT,
          ),
        ),
    );
    CRM_Core_DAO::executeQuery('SET SESSION group_concat_max_len = 1000000');

  }

  function groupBy() {
    $this->_groupBy = 'GROUP BY entity_log_civireport.log_conn_id, entity_log_civireport.log_user_id, EXTRACT(DAY_MICROSECOND FROM entity_log_civireport.log_date)';
    $groupBys = array();
    if (CRM_Utils_Array::value('group_bys', $this->_params) &&
        is_array($this->_params['group_bys']) &&
        !empty($this->_params['group_bys'])
    ) {
      foreach ($this->_columns as $tableName => $table) {
        if (array_key_exists('group_bys', $table)) {
          foreach ($table['group_bys'] as $fieldName => $field) {
            if (CRM_Utils_Array::value($fieldName, $this->_params['group_bys'])) {
              if(CRM_Utils_Array::value('frequency',$field) == 1){
                $groupBys[] = "EXTRACT({$this->_params['group_bys_freq'][$fieldName]} FROM  {$field['dbAlias']})";
                $this->timeInterval = $this->_params['group_bys_freq'][$fieldName];
              }
              else{
                $groupBys[] = $field['dbAlias'];
              }
            }
          }
        }
      }
    }

    if (!empty($groupBys)) {
      $this->_groupBy = "GROUP BY " . implode(', ', $groupBys);
    }
  }

  function orderBy() {
    if(empty($this->_params['group_bys'])){
      $this->_orderBy = 'ORDER BY entity_log_civireport.log_date DESC';
    }
  }

  function alterDisplay(&$rows) {
    // cache for id → is_deleted mapping
    $isDeleted = array();
    $newRows   = array();

    foreach ($rows as $key => &$row) {
      if (!isset($isDeleted[$row['log_civicrm_entity_altered_contact_id']])
          && !empty($row['log_civicrm_entity_altered_contact_id'])) {
        $isDeleted[$row['log_civicrm_entity_altered_contact_id']] =
          CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $row['log_civicrm_entity_altered_contact_id'], 'is_deleted') !== '0';
      }

      if (!empty($row['log_civicrm_entity_altered_contact_id']['is_deleted'])) {
        if(strpos($row['log_civicrm_entity_altered_contact_id'], $this->_groupConcatSeparator) !== FALSE){
          $alteredContacts = explode(',', $row['log_civicrm_entity_altered_contact_id']);
          $alteredContactsName = explode(',', $row['log_civicrm_entity_altered_contact']);
          $alterContactsDetail = array();
          $alteredContactsStr = '';

          foreach ($alteredContacts as $index => $cid){
            if(!array_key_exists($cid, $alterContactsDetail)){
              // ie. don't display a given contact more than once
              $alteredContactsStr .=  "<a href=" . CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $cid ). ">" . $alteredContactsName[$index] . "</a>, ";
            }

            $alterContactsDetail[$cid] = $alteredContactsName[$index];
          }
          $row['log_civicrm_entity_altered_contact'] = rtrim($alteredContactsStr,', ');
        }
        else{
        $row['log_civicrm_entity_altered_contact_link'] =
          CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $row['log_civicrm_entity_altered_contact_id']);
        $row['log_civicrm_entity_altered_contact_hover'] = ts("Go to contact summary");
        $entity = $this->getEntityValue($row['log_civicrm_entity_id'], $row['log_civicrm_entity_log_type'], $row['log_civicrm_entity_log_date']);        if ($entity)
          $row['log_civicrm_entity_altered_contact'] = $row['log_civicrm_entity_altered_contact'] . " [{$entity}]";
        }
      }
      if(strpos($row['log_civicrm_entity_log_user_id'], $this->_groupConcatSeparator) !== FALSE){

      }
      $row['altered_by_contact_display_name_link'] = CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $row['log_civicrm_entity_log_user_id']);
      $row['altered_by_contact_display_name_hover'] = ts("Go to contact summary");

      if ($row['log_civicrm_entity_is_deleted'] and $row['log_civicrm_entity_log_action'] == 'Update') {
        $row['log_civicrm_entity_log_action'] = ts('Delete (to trash)');
      }

      if ('Contact' == CRM_Utils_Array::value('log_type', $this->_logTables[$row['log_civicrm_entity_log_type']]) &&
          $row['log_civicrm_entity_log_action'] == 'Insert' ) {
        $row['log_civicrm_entity_log_action'] = ts('Update');
      }

      if (strpos($row['log_civicrm_entity_id'],',') !== FALSE && $newAction = $this->getEntityAction($row['log_civicrm_entity_id'], $row['log_civicrm_entity_log_conn_id'], $row['log_civicrm_entity_log_type']))
        $row['log_civicrm_entity_log_action'] = $newAction;
      $logType = empty($row['log_civicrm_entity_log_type']) ? '' : $row['log_civicrm_entity_log_type'];
      $row['log_civicrm_entity_log_type'] = $this->getLogType($logType );

    //  if ($row['log_civicrm_entity_log_action'] == 'Update') {
        $q = "reset=1&force=1&log_conn_id={$row['log_civicrm_entity_log_conn_id']}&log_date={$row['log_civicrm_entity_log_date']}&time_interval={$this->timeInterval}";
        if ($this->cid) {
          $q .= '&cid=' . $this->cid;
        }

        $url = CRM_Report_Utils_Report::getNextUrl('contact/extendedloggingdetail', $q, FALSE);
        $row['log_civicrm_entity_log_action_link'] = $url;
        $row['log_civicrm_entity_log_action_hover'] = ts("View details for this update");
        $row['log_civicrm_entity_log_action'] = '<div class="icon details-icon"></div> ' . ts($row['log_civicrm_entity_log_action']);
   //   }

      $date = CRM_Utils_Date::isoToMysql($row['log_civicrm_entity_log_date']);
      $key  = $date . '_' . $row['log_civicrm_entity_log_type'] . '_' . $row['log_civicrm_entity_log_conn_id'] . '_' . $row['log_civicrm_entity_log_user_id'];
      $newRows[$key] = $row;

      unset($row['log_civicrm_entity_log_user_id']);
      unset($row['log_civicrm_entity_log_conn_id']);
    }

    krsort($newRows);
    $rows = $newRows;
  }

  /*
   * field specific SELECT
   */
  function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    if(!empty($this->_params['group_bys']) && is_array($this->_params['group_bys']) && array_key_exists($fieldName, $this->_params['group_bys'])){
      if($fieldName == 'log_date'){
        $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
        $this->_columnHeaders["{$tableName}_{$fieldName}"]['no_display'] = CRM_Utils_Array::value('no_display', $field);
        $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = CRM_Utils_Array::value('title', $field);
      //  return " CONCAT('from ' , min({$field['dbAlias']}), ' to ', max({$field['dbAlias']})) as {$tableName}_{$fieldName}";
      }
    }
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);

  }

}

