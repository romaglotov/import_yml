<?php
    ## Подключение к базе данных
    $mysqli = new mysqli('localhost', 'username', 'password', 'database');
    if (mysqli_connect_errno()) {
        printf("Соединение не установлено: %s\n", mysqli_connect_error());
        exit();
    }

	$query = $mysqli->query("SELECT c.category_id, c.parent_id, cd.name FROM bsc_category c INNER JOIN bsc_category_description cd ON c.category_id = cd.category_id");


	$categories_db = array ();

	$cat_db_list = "";

	while ($row = $query->fetch_assoc()) {
		$categories_db[$row['category_id']] = array (
			'category_id'		=> $row['category_id'],
			'name'		=> $row['name'],
			'parent_id'	=> $row['parent_id']
		);
	}

	foreach ($categories_db as $category_db) {
		$cat_db_list .= "<option value='".$category_db['category_id']."'>".$category_db['name'];
		if ($category_db['parent_id'] != 0) {
			$i = $category_db['parent_id'];
			$cat_db_list .= "->".$categories_db[$i]['name'];
			if ($categories_db[$i]['parent_id'] != 0) {
				$j = $categories_db[$i]['parent_id'];
				$cat_db_list .= "->".$categories_db[$j]['name'];
			}
		}
		$cat_db_list .= "</option>";
	}
	$cat_db_list .="</select>";
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Import YML Files</title>

		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
		<script src="http://code.jquery.com/jquery-3.3.1.js" integrity="sha256-2Kok7MbOyxpgUVvAk/HJ2jigOSYS2auK4Pfzbm7uH60=" crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

	</head>
	<body>

		<div class="container">
			<div class="row justify-content-center">
				<div class="col-lg-auto">
					<h1>Импорт товаров, категорий и производителей YML</h1>
				</div>
			</div>

			<div class="row justify-content-center">
				<div class="col-lg-auto">
					<form name="import" class="import" method="post" action="" id="import">
						<input type="text" name="url_input" id="url_input">
						<input type="button" name="url_submit" id="url_submit" value="Отправить">
					</form>
				</div>
			</div>

			<div class="row justify-content-center">
				<div class="col-lg-auto">
					<p class="processing">
					</p>
					<p class="result_import_url">
					</p>
				</div>
			</div>

			<div class="import_settings" style="display: none;">
				<div class="row justify-content-center">
					<div class="col-lg-12">
						<p><button id="category_import_button">Импорт категорий</button></p>
					</div>

					<div class="col-lg-12">
						<p class="processing_category"></p>
						<form name="import_category" class="import_category" method="post" action="" id="import_category" style="display: none;">
							<h5>Выбрать категории для импорта:</h5>
							<p class="category_list">
							</p>
							<h5>Дополнительные опции импорта:</h5>
							<p>
								<input type="hidden" name="file_name" value="" class="file_name">
								<input type="hidden" name="category_marker" value="True">
								<input type="checkbox" name="menu_category" value="True" id="menu_category">
								<label for="menu_category">Использовать родительские категории в качестве навигации?</label><br>
								<br>
							</p>
							<p><input type="button" name="category_submit" id="category_submit" value="Импортировать категории"></p>
						</form>
					</div>

					<div class="col-lg-12">
						<p><button id="product_import_button">Импорт товаров</button></p>
					</div>

					<div class="col-lg-12">
						<p class="processing_product"></p>
						<form name="import_products" class="import_products" method="post" action="" id="import_products" style="display: none;">
							<h5>Сопоставление категорий</h5>
							<p class="category_list_rel">
							</p>
							<h5>Основные настройки</h5>
							<p>
								<input type="hidden" name="file_name" value="" class="file_name">
								<input type="hidden" name="product_marker" value="True">
								<!--
								<input type="checkbox" name="typeId" id="groupProducts" value="True">
								<label for="groupProducts">Объединить группы товаров в один товар?</label><br>
								<input type="checkbox" name="manufacturer" id="manufacturer" value="True">
								<label for="manufacturer">Импортировать производителей?</label><br>
								<input type="checkbox" name="recomend" id="recomend" value="True">
								<label for="recomend">Использовать группы товаров с одинаковым названием как рекомендации или как товар в другом цвете?</label><br>
								<input type="checkbox" name="descriptionProduct" id="descriptionProduct" value="True">
								<label for="descriptionProduct">Сформировать из оставшихся параметров таблицу для описания?</label><br>
								<input type="checkbox" name="doTitle" id="doTitle" value="True">
								<label for="doTitle">Использовать имя товара в качестве SEO-title?</label><br>
								<input type="checkbox" name="doDescription" id="doDescription" value="True">
								<label for="doDescription">Использовать базовое описание товара в качестве meta-description?</label><br>
								<select name="typeImportProduct">
									<option value="addProduct">Добавить только новые товары</option>
									<option value="updateProduct">Обновить старые и добавить новые</option>
									<option value="clearProduct">Удалить все товары и добавить новые</option>
								</select>
								-->
							</p>
							<h5>Сопоставление полей</h5>
							<p class="params_list">
							</p>
							<p><input type="button" name="product_submit" id="product_submit" value="Импортировать товары"></p>
						</form>
					</div>
				</div>
			</div>
		</div>


	</body>
</html>

<script type="text/javascript">
	$(document).ready(function () {
		$('#url_submit').click(function () {
			data = $('#import').serialize();
			$.ajax({
				type: 'POST',
				url: 'yml_ajax.php',
				dataType: 'html',
				data: data,
				success: function(response) {
					result = $.parseJSON(response);
					console.log(result.error);
					$('.processing').html(result.answer);
					$('.file_name').attr('value', result.url);
					$('.import_settings').show();
					categoriesResponse (result.categories);
					paramsResponse(result.params);
				},
				error: function(response) {
					$('.result_import').html('Error');
				}
			});
			$('.processing').html('Loading and processing...');
			return false;
		});

		$('#category_import_button').click(function () {
			$('#import_category').toggle();
		});

		$('#product_import_button').click(function () {
			$('#import_products').toggle();
		});

		$('#product_submit').click(function () {
			data = $('#import_products').serialize();
			$('.processing_product').html('Loading and processing...');
			$.ajax({
				type: 'POST',
				url: 'yml_ajax.php',
				dataType: 'html',
				data: data,
				success: function (response) {
					console.log (response);
					$('.processing_product').html('Ready!');
				},
				error: function (xhr, ajaxOptions, thrownError) {
					alert(xhr.status);
					alert(thrownError);
				}
			});
		});

		$('#category_submit').click(function () {
			data = $('#import_category').serialize();
			$('.processing_category').html('Loading and processing...');
			$.ajax({
				type: 'POST',
				url: 'yml_ajax.php',
				dataType: 'html',
				data: data,
				success: function (response) {
					console.log (response);
					$('.processing_category').html('Ready!');
				},
				error: function (xhr, ajaxOptions, thrownError) {
					alert(xhr.status);
					alert(thrownError);
				}
			});
		});
	});

	function categoriesResponse (data) {
		categories = data;
		cat = '';
		rel_cat = '';
		for (var key in categories) {
			cat += "<input type='checkbox' name='add_cat_id["+key+"][import]' class='cat_id' id='c"+key+"'value='"+key+"'><label for='c"+key+"'>"+categories[key]['name'];
			rel_cat += "<p id=rc"+key+"><input type='checkbox' name='rel_cat_id["+key+"][import]' value ='True' class='rel_cat_id' id='r"+key+"' checked='checked'><label for='r"+key+"'>"+categories[key]['name'];
			if (categories[key]['parent_id'] != 0) {
				i = categories[key]['parent_id'];
				cat += '->'+categories[i]['name'];
				rel_cat += '->'+categories[i]['name'];
				if (categories[i]['parent_id'] != 0) {
					j = categories[i]['parent_id'];
					cat += '->'+categories[j]['name'];
					rel_cat += '->'+categories[j]['name'];
				}
			}
			cat += "</label><br />";
			rel_cat += "</label><br /><select size='1' name='rel_cat_id["+key+"][base]'><?php echo $cat_db_list; ?></p>";
		}
		$('.category_list').html(cat);
		$('.category_list_rel').html(rel_cat);
	}

	function paramsResponse (data) {
		params = data;
		params_list = '';
		for (var key in params) {
			params_list +='<option value="'+key+'">'+key+'</option>';
		}

		rel_params = '';
		rel_params += '<p><label for="product_name">Название товара</label><br><select name="product_name" id="product_name">'+params_list+'</select></p>';
		rel_params += '<p><label for="product_model">Модель</label><br><select name="product_model" id="product_model">'+params_list+'</select></p>';
		rel_params += '<p><label for="product_description">Описание</label><br><select name="product_description" id="product_description">'+params_list+'</select></p>';
		rel_params += '<p><label for="product_price">Цена продажи</label><br><select name="product_price" id="product_price">'+params_list+'</select></p>';
		rel_params += '<p><label for="product_manufacturer">Производитель</label><br><select name="product_manufacturer" id="product_manufacturer">'+params_list+'</select></p>';
		rel_params += '<p><label for="product_category">Категория</label><br><select name="product_category" id="product_category">'+params_list+'</select></p>';
		rel_params += '<p><label for="product_picture">Изображения</label><br><select name="product_picture" id="product_picture">'+params_list+'</select></p>';
		rel_params += '<p><label for="product_colour">Цвет</label><br><select name="product_colour" id="product_colour">'+params_list+'</select></p>';
		rel_params += '<p><label for="product_size">Размер</label><br><select name="product_size" id="product_size">'+params_list+'</select></p>';
		//$('.params_list').html(rel_params);
	}

</script>