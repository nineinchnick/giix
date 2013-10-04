<?php

/**
 * GiixCrudCode class file.
 *
 * @author Rodrigo Coelho <rodrigo@giix.org>
 * @link http://giix.org/
 * @copyright Copyright &copy; 2010-2011 Rodrigo Coelho
 * @license http://giix.org/license/ New BSD License
 */
Yii::import('system.gii.generators.crud.CrudCode');
Yii::import('giix.helpers.*');

/**
 * GiixCrudCode is the model for giix crud generator.
 *
 * @author Rodrigo Coelho <rodrigo@giix.org>
 * @package giix.generators.giixCrud
 */
class GiixCrudCode extends CrudCode {

	/**
	 * @var string The type of authentication.
	 */
	public $authtype = 'auth_none';
	/**
	 * @var int Specifies if ajax validation is enabled. 0 represents false, 1 represents true.
	 */
	public $enable_ajax_validation = 0;
	/**
	 * @var string The controller base class name.
	 */
	public $baseControllerClass = 'GxController';

	/**
	 * @var string The module to which the class belongs.
	 */
	public $moduleName = '';

	/**
	 * Adds the new model attributes (class properties) to the rules.
	 * #MethodTracker
	 * This method overrides {@link CrudCode::rules}, from version 1.1.7 (r3135). Changes:
	 * <ul>
	 * <li>Adds the rules for the new attributes in the code generation form: authtype; enable_ajax_validation.</li>
	 * </ul>
	 */
	public function rules() {
		return array_merge(parent::rules(), array(
			array('authtype, enable_ajax_validation', 'required'),
			array('moduleName', 'filter', 'filter'=>'trim'),
			array('moduleName', 'match', 'pattern'=>'/^(\w+[\w\.]*|\*?|\w+\.\*)$/', 'message'=>'{attribute} should only contain word characters, dots, and an optional ending asterisk.'),
			array('moduleName', 'sticky'),
		));
	}

	/**
	 * Sets the labels for the new model attributes (class properties).
	 * #MethodTracker
	 * This method overrides {@link CrudCode::attributeLabels}, from version 1.1.7 (r3135). Changes:
	 * <ul>
	 * <li>Adds the labels for the new attributes in the code generation form: authtype; enable_ajax_validation.</li>
	 * </ul>
	 */
	public function attributeLabels() {
		return array_merge(parent::attributeLabels(), array(
			'authtype' => 'Authentication type',
			'enable_ajax_validation' => 'Enable ajax validation',
		));
	}

	public function getModule()
	{
		if(($pos=strpos($this->controller,'/'))!==false)
		{
			$id=substr($this->controller,0,$pos);
			if(($module=Yii::app()->getModule($id))!==null)
				return $module;
		}else
			if(!empty($this->moduleName))
				return Yii::app()->getModule($this->moduleName);
		return Yii::app();
	}

	public function getControllerID()
	{
		if(!empty($this->model) && !empty($this->moduleName))
			return $this->controller;

		return parent::getControllerID();
	}

	public function validateModel($attribute,$params)
	{
		$actualModel = $this->model; 
		if(!empty($this->model) && !empty($this->moduleName))
			$this->model = 'application.modules.'.$this->moduleName.'.models.'.$this->model;
		parent::validateModel($attribute,$params);
		$this->model = $actualModel;
	}

	/**
	 * Generates and returns the view source code line
	 * to create the appropriate active input field based on
	 * the model attribute field type on the database.
	 * #MethodTracker
	 * This method is based on {@link CrudCode::generateActiveField}, from version 1.1.7 (r3135). Changes:
	 * <ul>
	 * <li>All styling is removed.</li>
	 * </ul>
	 * @param string $modelClass The model class name.
	 * @param CDbColumnSchema $column The column.
	 * @return string The source code line for the active field.
	 */
	public function generateActiveField($modelClass, $column) {
		if ($column->isForeignKey) {
			$relation = $this->findRelation($modelClass, $column);
			$relatedModelClass = $relation[3];
			return "echo \$form->dropDownList(\$model, '{$column->name}', GxHtml::listDataEx({$relatedModelClass}::model()->findAllAttributes(null, true)))";
		}

		if (strtoupper($column->dbType) == 'TINYINT(1)'
				|| strtoupper($column->dbType) == 'BIT'
				|| strtoupper($column->dbType) == 'BOOL'
				|| strtoupper($column->dbType) == 'BOOLEAN') {
			return "echo \$form->checkBox(\$model, '{$column->name}')";
		} else if (strtoupper($column->dbType) == 'DATE') {
			return "\$form->widget('zii.widgets.jui.CJuiDatePicker', array(
			'model' => \$model,
			'attribute' => '{$column->name}',
			'value' => \$model->{$column->name},
			'options' => array(
				'showButtonPanel' => true,
				'changeYear' => true,
				'dateFormat' => 'yy-mm-dd',
				),
			));\n";
		} else if (stripos($column->dbType, 'text') !== false) { // Start of CrudCode::generateActiveField code.
			return "echo \$form->textArea(\$model, '{$column->name}')";
		} else {
			$passwordI18n = Yii::t('app', 'password');
			$passwordI18n = (isset($passwordI18n) && $passwordI18n !== '') ? '|' . $passwordI18n : '';
			$pattern = '/^(password|pass|passwd|passcode' . $passwordI18n . ')$/i';
			if (preg_match($pattern, $column->name))
				$inputField = 'passwordField';
			else
				$inputField='textField';

			if ($column->type !== 'string' || $column->size === null)
				return "echo \$form->{$inputField}(\$model, '{$column->name}')";
			else
				return "echo \$form->{$inputField}(\$model, '{$column->name}', array('maxlength' => {$column->size}))";
		} // End of CrudCode::generateActiveField code.
	}

	/**
	 * Generates and returns the view source code line
	 * to create the appropriate active input field based on
	 * the model relation.
	 * @param string $modelClass The model class name.
	 * @param array $relation The relation details in the same format
	 * used by {@link getRelations}.
	 * @return string The source code line for the relation field.
	 * @throws InvalidArgumentException If the relation type is not HAS_ONE, HAS_MANY or MANY_MANY.
	 */
	public function generateActiveRelationField($modelClass, $relation) {
		$relationName = $relation[0];
		$relationType = $relation[1];
		$relationField = $relation[2]; // The FK.
		$relationModel = $relation[3];
		// The relation type must be HAS_ONE, HAS_MANY or MANY_MANY.
		// Other types (BELONGS_TO) should be generated by generateActiveField.
		if ($relationType != GxActiveRecord::HAS_ONE && $relationType != GxActiveRecord::HAS_MANY && $relationType != GxActiveRecord::MANY_MANY)
			throw new InvalidArgumentException(Yii::t('giix.messages', 'The argument "relationName" must have a relation type of HAS_ONE, HAS_MANY or MANY_MANY.'));

		// Generate the field according to the relation type.
		switch ($relationType) {
			case GxActiveRecord::HAS_ONE:
				return "echo \$form->dropDownList(\$model, '{$relationName}', GxHtml::listDataEx({$relationModel}::model()->findAllAttributes(null, true)))";
				break;
			case GxActiveRecord::HAS_MANY:
			case GxActiveRecord::MANY_MANY:
				return "echo \$form->checkBoxList(\$model, '{$relationName}', GxHtml::encodeEx(GxHtml::listDataEx({$relationModel}::model()->findAllAttributes(null, true)), false, true))";
				break;
		}
	}

	public function generateInputField($modelClass, $column) {
		return 'echo ' . parent::generateInputField($modelClass, $column);
	}

	/**
	 * Generates and returns the view source code line
	 * to create the appropriate attribute configuration for a CDetailView.
	 * @param string $modelClass The model class name.
	 * @param CDbColumnSchema $column The column.
	 * @return string The source code line for the attribute.
	 */
	public function generateDetailViewAttribute($modelClass, $column) {
		if (!$column->isForeignKey) {
			if (strtoupper($column->dbType) == 'TINYINT(1)'
					|| strtoupper($column->dbType) == 'BIT'
					|| strtoupper($column->dbType) == 'BOOL'
					|| strtoupper($column->dbType) == 'BOOLEAN') {
				return "'{$column->name}:boolean'";
			} else
				return "'{$column->name}'";
		} else {
			// Find the relation name for this column.
			$relation = $this->findRelation($modelClass, $column);
			$relationName = $relation[0];
			$relatedModelClass = $relation[3];
			$relatedControllerName = strtolower($relatedModelClass[0]) . substr($relatedModelClass, 1);

			return "array(
			'name' => '{$relationName}',
			'type' => 'raw',
			'value' => \$model->{$relationName} !== null ? GxHtml::link(GxHtml::encode(GxHtml::valueEx(\$model->{$relationName})), array('{$relatedControllerName}/view', 'id' => GxActiveRecord::extractPkValue(\$model->{$relationName}, true))) : null,
			)";
		}
	}

	/**
	 * Generates and returns the view source code line
	 * to create the CGridView column definition.
	 * @param string $modelClass The model class name.
	 * @param CDbColumnSchema $column The column.
	 * @return string The source code line for the column definition.
	 */
	public function generateGridViewColumn($modelClass, $column) {
		if (!$column->isForeignKey) {
			// Boolean or bit.
			if (strtoupper($column->dbType) == 'TINYINT(1)'
					|| strtoupper($column->dbType) == 'BIT'
					|| strtoupper($column->dbType) == 'BOOL'
					|| strtoupper($column->dbType) == 'BOOLEAN') {
				return "array(
					'name' => '{$column->name}',
					'value' => '(\$data->{$column->name} === 0) ? \\'No\\' : \\'Yes\\'',
					'filter' => array('0' => 'No', '1' => 'Yes'),
					)";
			} else // Common column.
				return "'{$column->name}'";
		} else { // FK.
			// Find the related model for this column.
			$relation = $this->findRelation($modelClass, $column);
			$relationName = $relation[0];
			$relatedModelClass = $relation[3];
			return "array(
				'name'=>'{$column->name}',
				'value'=>'GxHtml::valueEx(\$data->{$relationName})',
				'filter'=>GxHtml::listDataEx({$relatedModelClass}::model()->findAllAttributes(null, true)),
				)";
		}
	}

	/**
	 * Generates and returns the view source code line
	 * to create the advanced search.
	 * @param string $modelClass The model class name.
	 * @param CDbColumnSchema $column The column.
	 * @return string The source code line for the column definition.
	 */
	public function generateSearchField($modelClass, $column) {
		if (!$column->isForeignKey) {
			// Boolean or bit.
			if (strtoupper($column->dbType) == 'TINYINT(1)'
					|| strtoupper($column->dbType) == 'BIT'
					|| strtoupper($column->dbType) == 'BOOL'
					|| strtoupper($column->dbType) == 'BOOLEAN')
				return "echo \$form->dropDownList(\$model, '{$column->name}', array('0' => 'No', '1' => 'Yes'), array('prompt' => 'All'))";
			else // Common column. generateActiveField method will add 'echo' when necessary.
				return $this->generateActiveField($this->modelClass, $column);
		} else { // FK.
			// Find the related model for this column.
			$relation = $this->findRelation($modelClass, $column);
			$relatedModelClass = $relation[3];
			return "echo \$form->dropDownList(\$model, '{$column->name}', GxHtml::listDataEx({$relatedModelClass}::model()->findAllAttributes(null, true)), array('prompt' => 'All'))";
		}
	}

	/**
	 * Generates and returns the array (as a PHP source code string)
	 * to collect the MANY_MANY related data from the POST.
	 * @param string $modelClass The model class name.
	 * @return string The source code to collect the MANY_MANY related
	 * data from the POST.
	 */
	public function generateGetPostRelatedData($modelClass, $indent = 1) {
		$result = array();
		$relations = $this->getRelations($modelClass);
		foreach ($relations as $relationData) {
			$relationName = $relationData[0];
			$relationType = $relationData[1];
			if ($relationType == GxActiveRecord::MANY_MANY)
				$result[$relationData[0]] = "php:\$_POST['{$modelClass}']['{$relationName}'] === '' ? null : \$_POST['{$modelClass}']['{$relationName}']";
		}
		return GxCoreHelper::ArrayToPhpSource($result, $indent);
	}

	/**
	 * Checks whether this AR has a MANY_MANY relation.
	 * @param string $modelClass The model class name.
	 * @return boolean Whether this AR has a MANY_MANY relation.
	 */
	public function hasManyManyRelation($modelClass) {
		$relations = $this->getRelations($modelClass);
		foreach ($relations as $relationData) {
			if ($relationData[1] == GxActiveRecord::MANY_MANY) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Finds the relation of the specified column.
	 * Note: There's a similar method in the class GxActiveRecord.
	 * @param string $modelClass The model class name.
	 * @param CDbColumnSchema $column The column.
	 * @return array The relation. The array will have 3 values:
	 * 0: the relation name,
	 * 1: the relation type (will always be GxActiveRecord::BELONGS_TO),
	 * 2: the foreign key (will always be the specified column),
	 * 3: the related active record class name.
	 * Or null if no matching relation was found.
	 */
	public function findRelation($modelClass, $column) {
		if (!$column->isForeignKey)
			return null;
		$relations = GxActiveRecord::model($modelClass)->relations();
		// Find the relation for this attribute.
		foreach ($relations as $relationName => $relation) {
			// For attributes on this model, relation must be BELONGS_TO.
			if ($relation[0] == GxActiveRecord::BELONGS_TO && $relation[2] == $column->name) {
				return array(
					$relationName, // the relation name
					$relation[0], // the relation type
					$relation[2], // the foreign key
					$relation[1] // the related active record class name
				);
			}
		}
		// None found.
		return null;
	}

	/**
	 * Returns all the relations of the specified model.
	 * @param string $modelClass The model class name.
	 * @return array The relations. Each array item is
	 * a relation as an array, having 3 items:
	 * 0: the relation name,
	 * 1: the relation type,
	 * 2: the foreign key,
	 * 3: the related active record class name.
	 * Or an empty array if no relations were found.
	 */
	public function getRelations($modelClass) {
		$relations = GxActiveRecord::model($modelClass)->relations();
		$result = array();
		foreach ($relations as $relationName => $relation) {
			$result[] = array(
				$relationName, // the relation name
				$relation[0], // the relation type
				$relation[2], // the foreign key
				$relation[1] // the related active record class name
			);
		}
		return $result;
	}

	/**
	 * Returns the message to be displayed when the newly generated code is saved successfully.
	 * #MethodTracker
	 * This method overrides {@link CrudCode::successMessage}, from version 1.1.7 (r3135). Changes:
	 * <ul>
	 * <li>Custom giix success message.</li>
	 * </ul>
	 * @return string The message to be displayed when the newly generated code is saved successfully.
	 */
	public function successMessage() {
		return <<<EOM
<p><strong>Sweet!</strong></p>
<ul style="list-style-type: none; padding-left: 0;">
	<li><img src="http://giix.org/icons/love.png"> Show how you love giix on <a href="http://www.yiiframework.com/forum/index.php?/topic/13154-giix-%E2%80%94-gii-extended/">the forum</a> and on its <a href="http://www.yiiframework.com/extension/giix">extension page</a></li>
	<li><img src="http://giix.org/icons/vote.png"> Upvote <a href="http://www.yiiframework.com/extension/giix">giix</a></li>
	<li><img src="http://giix.org/icons/powered.png"> Show everybody that you are using giix in <a href="http://www.yiiframework.com/forum/index.php?/topic/19226-powered-by-giix/">Powered by giix</a></li>
	<li><img src="http://giix.org/icons/donate.png"> <a href="http://giix.org/">Donate</a></li>
</ul>
<p style="margin: 2px 0; position: relative; text-align: right; top: -15px; color: #668866;">icons by <a href="http://www.famfamfam.com/lab/icons/silk/" style="color: #668866;">famfamfam.com</a></p>
EOM;
	}

	/**
	 * The above functions are from BootStrapCode class.
	 * BootstrapCode class file.
	 * @author Christoffer Niska <ChristofferNiska@gmail.com>
	 * @copyright Copyright &copy; Christoffer Niska 2013-
	 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
	 * @package bootstrap.gii
	 */
	public function generateControlGroup($modelClass, $column)
    {
        if ($column->type === 'boolean') {
            return "TbHtml::activeCheckBoxControlGroup(\$model,'{$column->name}')";
        } else {
            if (stripos($column->dbType, 'text') !== false) {
                return "TbHtml::activeTextAreaControlGroup(\$model,'{$column->name}',array('rows'=>6,'span'=>8))";
            } else {
                if (preg_match('/^(password|pass|passwd|passcode)$/i', $column->name)) {
                    $inputField = 'activePasswordControlGroup';
                } else {
                    $inputField = 'activeTextFieldControlGroup';
                }

                if ($column->type !== 'string' || $column->size === null) {
                    return "TbHtml::{$inputField}(\$model,'{$column->name}')";
                } else {
                    if (($size = $maxLength = $column->size) > 60) {
                        $size = 60;
                    }
                    return "TbHtml::{$inputField}(\$model,'{$column->name}',array('size'=>$size,'maxlength'=>$maxLength))";
                }
            }
        }
    }

    public function generateActiveControlGroup($modelClass, $column)
    {
        if ($column->type === 'boolean') {
            return "\$form->checkBoxControlGroup(\$model,'{$column->name}')";
        } else {
            if (stripos($column->dbType, 'text') !== false) {
                return "\$form->textAreaControlGroup(\$model,'{$column->name}',array('rows'=>6,'span'=>8))";
            } else {
                if (preg_match('/^(password|pass|passwd|passcode)$/i', $column->name)) {
                    $inputField = 'passwordFieldControlGroup';
                } else {
                    $inputField = 'textFieldControlGroup';
                }

                if ($column->type !== 'string' || $column->size === null) {
                    return "\$form->{$inputField}(\$model,'{$column->name}',array('span'=>5))";
                } else {
                    return "\$form->{$inputField}(\$model,'{$column->name}',array('span'=>5,'maxlength'=>$column->size))";
                }
            }
        }
    }
}