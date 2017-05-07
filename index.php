<?php
/**
 *	@author Leonardo H. Noering. ( lnoering@gmail.com )
 *	@copyright 10/11/2015
 */
?>

<?php if (!empty($_POST)): ?>
	<?php
		$ld = new List_Databases();
		echo $ld->updateRow($_POST);
	?>
<?php else: ?>

	<!DOCTYPE html>
	<html>
		<head>
			<title>Lista de BD`s ;)</title>
			<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
			<style type="text/css">
				#container {
			    	width: 100%;
			    	border-color: blue;
				    text-align: center;
				}

				.box {
				    float: left;
				    /*margin: 10px 20px;*/
				    width: 18%;
				    overflow-x: auto;
				    text-align: left;
				    /*border-right: 1px solid #000;*/
				}
				.box:nth-child(even) {
					background-color: #c1c1c1;
				}
				.box:nth-child(odd) {
					background-color: #dcdcdc;
				}
				.box h1{
					text-align: center;
				}

				.box label {
					border-bottom: 1px solid black;
					font-weight: bold;
				}

			</style>
		</head>
		<body>
			<div id='container' align="center">
				<?php
				$ld = new List_Databases();
				foreach ($ld->getApplicationDatabases() as $key_db_tipo => $tipos) {
				?>
					<div class='box'>
						<h1><?php echo strtoupper($key_db_tipo)  ?></h1>
						<ul>
						<?php
						foreach ($tipos as $key_db_name => $value) {
							if ($key_db_tipo == 'magento') {

								echo "<li data-related='".md5($key_db_name)."' >$key_db_name</li>";

								echo "<div id='".md5($key_db_name)."' style='display:none;'>";

								foreach ($value as $key_column => $inputs) {
									echo "<label>".$key_column."</label><br>";
									echo $inputs;
								}
								echo '</div>';
							} else {
								echo "<li >".$key_db_name."</li>";
							}
						}
						?>
						</ul>
					</div>
				<?php
				}
				?>
				</div>
			</div>
			<script type="text/javascript">
				$("ul li").on("click", function() {
				  	$("div[id=" + $(this).attr("data-related") + "]").toggle();
				});

				$("ul div input").on("keydown", function(event) {
					if(event.which == 13) {
					  	$.ajax({
					  			type:"POST",
					  			url: "<?php echo "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"; ?>",
					  			data: {valor: $(this).val(), bd:$(this).attr("data-bd"), tabela:$(this).attr("data-table"), id:$(this).attr("data-id")},
					  			success: function(retorno){
						        	var obj = JSON.parse(retorno);
						        	$(this).val(obj.result);
						    	}
							});
				  	}
				});
			</script>

		</body>
	</html>
<?php endif; ?>


<?php
class List_Databases
{
	private $_user 		= 'root';
	private $_password 	= 'root';
	private $_host 		= 'localhost';

	private $_conn 		= null;

	private $_keysCoreConfigData = array('web/%/base_url','admin/url/custom','web/cookie/cookie_domain','advanced/modules_disable_output/%');

	private $_keysToExplode = array('advanced/modules_disable_output/%'=>2);

	private $_showDatabases = null;

	public function __construct() {
		$this->_conn = new PDO('mysql:host=localhost;', 'root', 'root');
		$this->_conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
	}

	public function getShowDatabases() {
		return $this->_conn->query('SHOW DATABASES');
	}

	public function getDatabasesTypes() {
		return array('schema' => array(), 'magento' => array(), 'wp' => array(), 'sites' => array(), 'error' => array());
	}

	public function getApplicationDatabases() {
		$showDatabases = $this->getDatabasesTypes();

		foreach ($this->getShowDatabases() as $row) {
			if (preg_match("/_schema/i", $row['Database']) || $row['Database'] == 'mysql') {
				$showDatabases['schema'][$row['Database']] = '';
			} else {
				try {
					$mg = $this->_conn->query("SHOW TABLES FROM `{$row['Database']}` LIKE '%core_config_data'");
					$wp = $this->_conn->query("SHOW TABLES FROM `{$row['Database']}` LIKE '%term_taxonomy'");

					if ($mg->rowCount() > 0) {
						$table = $mg->fetchColumn();
						foreach ($this->_keysCoreConfigData as $column) {
							foreach ($this->_conn->query("SELECT * FROM `{$row['Database']}`.`{$table}` WHERE path LIKE '{$column}'") as $value) {
								if(array_key_exists($column, $this->_keysToExplode)) {
									$aux = explode('/',$value['path']);
									$value['path'] = array_pop($aux);
									unset($aux);
								}
								$showDatabases['magento'][$row['Database']][$column] .= "<span>".$value['scope']." / ".$value['path']."<span><br>";
								$showDatabases['magento'][$row['Database']][$column] .= "<input data-bd='".$row['Database']."' data-table='".$table."' data-id='".$value['config_id']."' type='text' value='".$value['value']."' />" . '</br>';
							}
						}
					} else if ($wp->rowCount() > 0) {
						$showDatabases['wp'][$row['Database']] = '';
					} else {
						$showDatabases['sites'][$row['Database']] = '';
					}
				} catch (exception $e){
					$showDatabases['error'][$row['Database']] = '';
				}
			}
		}

		return $showDatabases;
	}

	/**
	 *  @param $arr ['valor'] ['tabela'] ['bd'] ['id']
	 */
	public function updateRow(array $arr) {
		$result = array();

		try {
			$sql = "UPDATE {$arr['bd']}.{$arr['tabela']} SET value = :arrValue
		            WHERE config_id = :arrID";

		    $this->_conn->beginTransaction();

			$stmt = $this->_conn->prepare($sql);

			$stmt->bindParam(':arrValue', $arr['valor'], PDO::PARAM_STR);
			$stmt->bindParam(':arrID', $arr['id'], PDO::PARAM_INT);

			$stmt->execute();

			$this->_conn->commit();
		} catch (exception $e) {
			$result['success'] = 'false';
			$result['result'] = $e->getMessage();
			$this->_conn->rollBack();

			return json_encode($result);
		}

		$sql = "SELECT * FROM {$arr['bd']}.{$arr['tabela']} WHERE config_id = :arrID";

		$stmt = $this->_conn->prepare($sql);

		$stmt->execute(array(':arrID' => $arr['id']));

		$result['success'] = 'true';
		$select = $stmt->fetchAll();
		$result['result'] = $select[0]['value'];

		return json_encode($result);
	}
}
?>