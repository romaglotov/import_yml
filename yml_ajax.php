<?php
/*
	p - products
	pd - products description
	pl - products layout
	ps - products store
	po - products option
	i- products image
	o - options
	m - manufacturer
	c - categories
	cd - categories description
	cp - categories path
	cl - categories layout
	cs - categories store
	file_get_contents_curl - функция загрузки содержимого по ссылке
*/
	/*
		## Функция, которая загружает контент по ссылке и возвращает результат.
	*/
	function file_get_contents_curl($url) {	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;		
	}


	/*
		## Загрузка файла и первичная обработка.
	*/
	if (isset($_POST['url_input'])) {
		
		libxml_use_internal_errors(true);
		$url_input = $_POST['url_input'];
		$marker = False;

		/*
			## Проверка файла. Загрузка контента и сохранение его на сервер.
		*/
		$data = preg_replace("/&#?[a-z0-9]+;/i","", file_get_contents_curl($url_input));
		$data = file_get_contents_curl($url_input);

		if (!file_exists(basename($url_input))) {
			if (file_put_contents(basename($url_input), $data)) {
				$answer =  ' Файл успешно загружен';
				$marker = True;
			} else {
				$answer =  ' Файл не удалось загрузить';
			}
		} else {
			unlink(basename($url_input));
			//rename(basename($url_input), time().'temp_'.basename($url_input));
			if (file_put_contents(basename($url_input), $data)) {
				//$answer = 'Файл успешно загружен. Старая версия файла переименована.';
				$answer = 'Файл успешно загружен. Старая версия файла удалена.';
				$marker = True;
			} else {
				$answer = 'Файл не удалось загрузить. На сервере присутствует уже файл с таким именем.';
			}
		}

		/*
			## Обработка файла импорта
		*/
		if ($marker) {
			## Извлечение xml
			
			$xml = simplexml_load_string($data);
			if (!$xml) {
			    $err =  "Ошибка загрузки XML\n";
			    foreach(libxml_get_errors() as $error) {
			        $err .= $error->message;
			    }
			} else {
				
				## Загрузка списка категорий
				$categories = $xml->shop->categories->category;
				$cat_data = array();
				foreach ($categories as $category) {
					$cat_data[(int)$category['id']] = array(
						'name' 		=> (string)$category[0],
						'parent_id' => (int)$category['parentId'],
						'id'		=> (int)$category['id']
					);
				}
				## Загрузка списка параметров товаров
				$params = array(
					'id'						=> 'id товара',
					'group_id'					=> 'id группы товаров',
					'oldprice'					=> 'Старая розничная цена',
					'price'						=> 'Розничная цена',
					'price_trade'				=> 'Оптовая цена',
					'minimum_order_quantity'	=> 'Минимальное количество для заказа',
					'categoryId'				=> 'id категории',
					'picture'					=> 'Картинки',
					'vendor'					=> 'Производитель',
					'country_of_origin'			=> 'Страна производитель',
					'name'						=> 'Название товара',
					'description'				=> 'Описание товара'
				);
				foreach ($xml->shop->offers->offer as $offer) {
					foreach ($offer->param as $param) {
						$params[(string)$param['name']] = (string)$param['name'];
					}
				}
			}
		}

		$result = array (
			'answer' 		 => $answer,
			'url'	 		 => basename($url_input),
			'categories'	 => $cat_data,
			'params'		 => $params,
			'test'			 => (string)$xml->shop->name,
			'error'		 	 =>	$err

		);
		echo json_encode($result);
	}


	/*
		## Импорт категорий.
	*/
	if (isset($_POST['category_marker'])) {
		$mysqli = new mysqli('localhost', 'username', 'password', 'database');
		if (mysqli_connect_errno()) {
    		printf("Соединение не установлено: %s\n", mysqli_connect_error());
    		exit();
		}

		$xml = simplexml_load_file($_POST['file_name']);

		$cats = array ();

		$cats_list = $_POST['add_cat_id'];

		$query_c = 'INSERT INTO `bsc_category` (`category_id`, `parent_id`, `top`, `column`, `status`) VALUES ';
		$query_cd = 'INSERT INTO `bsc_category_description` (`category_id`, `language_id`, `name`, `meta_title`) VALUES ';
		$query_cp = 'INSERT INTO `bsc_category_path` (`category_id`, `path_id`, `level`) VALUES ';
		$query_cl = 'INSERT INTO `bsc_category_to_layout` (`category_id`, `store_id`, `layout_id`) VALUES ';
		$query_cs = 'INSERT INTO `bsc_category_to_store` (`category_id`, `store_id`) VALUES ';

		foreach ($xml->shop->categories->category as $category) {
			if ((int)$category['parentId'] == 0) {
				$query_cp .= '('.(int)$category['id'].', '.(int)$category['id'].', 0),';
				$cats[(int)$category['id']]['level'] = 0;
				$cats[(int)$category['id']]['parentId'] = (int)$category['parentId'];
			} else {
				$cats[(int)$category['id']]['level'] = $cats[(int)$category['parentId']]['level'] + 1;
				$cats[(int)$category['id']]['parentId'] = (int)$category['parentId'];
				$query_cp .= '('.(int)$category['id'].', '.(int)$category['id'].', '.$cats[(int)$category['id']]['level'].'),';
				$query_cp .= '('.(int)$category['id'].', '.(int)$category['parentId'].', '.$cats[(int)$category['parentId']]['level'].'),';
				if ((int)$cats[(int)$category['parentId']]['parentId'] != 0) {
					$query_cp .= '('.(int)$category['id'].', '.(int)$cats[(int)$category['parentId']]['parentId'].', 0),';
				}
			}
			if ($_POST['menu_category']) {
				if ($category['parentId'] == 0) {
					$top = 1;
				} else {
					$top = 0;
				}
			} else {
				$top = 0;
			}
			$query_c .= '('.(int)$category['id'].', '.(int)$category['parentId'].', '.$top.', 1, 1),';
			$query_cd .= '('.(int)$category['id'].', 1, "'.(string)$category[0].'", "'.(string)$category[0].'"),';
			$query_cl .= '('.(int)$category['id'].', 0, 3),';
			$query_cs .= '('.(int)$category['id'].', 0),';

		}
		$query_c = substr($query_c, 0, -1);
		$query_cd = substr($query_cd, 0, -1);
		$query_cp = substr($query_cp, 0, -1);
		$query_cl = substr($query_cl, 0, -1);
		$query_cs = substr($query_cs, 0, -1);
		$mysqli->query($query_c);
		$mysqli->query($query_cd);
		$mysqli->query($query_cp);
		$mysqli->query($query_cl);
		$mysqli->query($query_cs);
	}



	/*
		##	Импорт товаров.
	*/
	if (isset($_POST['product_marker'])) {

		$mem_start = memory_get_usage();

		## Обработка списка категорий
		$rel_cat_id = $_POST['rel_cat_id'];
		$cats_list = array();
		foreach ($rel_cat_id as $key => $value) {
			if ($value['import'] == 'True') {
				$cats_list[] = $key;
			}
		}
		unset($key, $value, $rel_cat_id);

		## Подключение к базе данных
		$mysqli = new mysqli('localhost', 'username', 'password', 'database');
		if (mysqli_connect_errno()) {
    		printf("Соединение не установлено: %s\n", mysqli_connect_error());
    		exit();
		}

		## Загрузка файла импорта
		$xml = simplexml_load_file($_POST['file_name']);

		## Инициализация переменных и массивов
		$option = array(); $option_db = array(); $product_option = array();
		$manufacturer_db = array(); $manufacturer = array();
		$products = array(); 
		$picture = array();

		## Выгрузка производителей из базы данных
		$query_m = 'SELECT `manufacturer_id`, `name` FROM `bsc_manufacturer`';
		$result = $mysqli->query($query_m);
		while ($row = $result->fetch_assoc()) {
			$manufacturer_db[$row['manufacturer_id']] = $row['name'];
		}
		unset($query_m, $result, $row);

		## Выгрузка списка опций
		$query_o = 'SELECT `option_value_id`, `name` FROM `bsc_option_value_description` WHERE `option_id`=19';
		$result = $mysqli->query($query_o);
		while ($row = $result->fetch_assoc()) {
			$option_db[$row['option_value_id']] = $row['name'];
		}
		unset($query_o, $result, $row);
		## Обработка файла импорта
		foreach ($xml->shop->offers->offer as $offer) {
			## Проверка товар на вхождение в выбранную категорию
			if (in_array((int)$offer->categoryId, $cats_list)) {
				## Обработка производителей
				if ($manufacturer_db) {
					if (!(in_array((string)$offer->vendor, $manufacturer_db))) {
						if (!(in_array((string)$offer->vendor, $manufacturer))) {
							$manufacturer[] = (string)$offer->vendor;
						}
					}
				} else  {
					if (!(in_array((string)$offer->vendor, $manufacturer))) {
						$manufacturer[] = (string)$offer->vendor;
					}
				}
				## Обработка товаров
				if (array_key_exists((int)$offer['group_id'], $products)) {
					## Если группа товаров уже обработана, то добавляем новое значение размера
					foreach ($offer->param as $param) {
						if ($param['name'] == 'Размер') {
							$product_option[(int)$offer['group_id']][] = (string)$param;
							if ($option_db) {
								if (!(in_array((string)$param, $option_db))) {
									if (!(in_array((string)$param, $option))) {
										$option[] = (string)$param;
									}
								}
							} else {
								if (!(in_array((string)$param, $option))) {
									$option[] = (string)$param;
								}
							}
						}
					}
					unset($param);
				} else {
					## Если группа товаров встречается впервые, то создаём элемент массива со значениями для товара
					## Обработка опций
					## Обработка параметров и создание описания
					$description = '<p>'.(string)$offer->description.'</p><ul>';
					foreach ($offer->param as $param) {
						if ($param['name'] == 'Размер') {
							$product_option[(int)$offer['group_id']][] = (string)$param;
							if ($option_db) {
								if (!(in_array((string)$param, $option_db))) {
									if (!(in_array((string)$param, $option))) {
										$option[] = (string)$param;
									}
								}
							} else {
								if (!(in_array((string)$param, $option))) {
									$option[] = (string)$param;
								}
							}
						} else {
							$description .= '<li>'.(string)$param['name'].': '.(string)$param.'</li>';
						}
					}
					$description .= '</ul>';
					unset($param);

					## Обработка изображений товара
					$picture[(int)$offer['group_id']] = array();
					foreach ($offer->picture as $pic) {
						$filename = basename((string)$pic);
						$filename = substr($filename, 0, strrpos($filename, '.'));
						$filename = 'catalog/'.$filename.'.jpg';

						$picture[(int)$offer['group_id']][] = $filename;
						//echo $pic;
						/*if (!file_exists($_SERVER['DOCUMENT_ROOT'].'/image/'.$filename)) {
							file_put_contents($_SERVER['DOCUMENT_ROOT'].'/image/'.$filename, file_get_contents_curl((string)$pic));
						} else {
							unlink($_SERVER['DOCUMENT_ROOT'].'/image/'.$filename);
							file_put_contents($_SERVER['DOCUMENT_ROOT'].'/image/'.$filename, $data);
						}
						unset($data);*/
					}
					unset ($pic);

					## SEO-URL товара
					$url = basename((string)$offer->picture[0]);
					$url = substr($url, 0, strrpos($url, '.'));
					$url = substr($url, 0, strrpos($url, '.'));

					$minimum_order_quantity = 1;
					if ((int)$offer->price > 0 and (int)$offer->price_trade > 0) {
						$dif = (int)$offer->price - (int)$offer->price_trade;
						if ($dif < 40 and $dif > 0) {
							$minimum_order_quantity = (int)(40/$dif);
							if ($minimum_order_quantity > 5) {
								$minimum_order_quantity = ceil($minimum_order_quantity/10)*10;
							}
						}
					}

					## Заполняем данные товара
					$products[(int)$offer['group_id']] = array(
						'product_id'				=> (int)$offer['group_id'],
						'model'						=> (int)$offer['group_id'],
						'image'						=> (string)$offer->picture[0],
						'manufacturer'				=> (string)$offer->vendor,
						'price'						=> (float)$offer->price,
						'start_price'				=> (float)$offer->price_trade,
						'name'						=> (string)$offer->name,
						'description'				=> (string)$description,
						'meta_title'				=> (string)$offer->name,
						'meta_description'			=> (string)$offer->description,
						'category_id'				=> (int)$offer->categoryId,
						'url'						=> $url,
						'minimum_order_quantity' 	=> $minimum_order_quantity
					);
					unset($description, $url);
				}
			}
		}
		echo "1";
		unset($xml, $offer, $cats_list, $option_db);
		## Обработка производителей
		if ($manufacturer) {
			$query_m = 'INSERT INTO `bsc_manufacturer` (`name`, `sort_order`) VALUES ';
			foreach ($manufacturer as $man) {
				$query_m .= '("'.$man.'", 0),';
			}
			$query_m = substr($query_m, 0, -1);
			$mysqli->query($query_m);
		}
		unset($query_m, $man);
		$query_m = 'SELECT `manufacturer_id`, `name` FROM `bsc_manufacturer`';
		$result_m = $mysqli->query($query_m);
		$man_list = array();
		while ($row = $result_m->fetch_assoc()) {
			$man_list[$row['manufacturer_id']] = $row['name'];
		}
		unset($query_m, $result_m, $row);
		if ($manufacturer) {
			$query_ms = 'SELECT `manufacturer_id` FROM `bsc_manufacturer_to_store`';
			$result_ms = $mysqli->query($query_ms);
			while ($row = $result_ms->fetch_assoc()) {
				$man_s_list[$row['manufacturer_id']] = $row['manufacturer_id'];
			}
			unset($query_ms, $result_ms, $row);
			$query_ms = 'INSERT INTO `bsc_manufacturer_to_store` (`manufacturer_id`, `store_id`) VALUES ';
			$query_u = 'INSERT INTO `bsc_url_alias` (`query`, `keyword`) VALUES ';
			foreach ($man_list as $key => $value) {
				if (!(in_array($key, $man_s_list))) {
					$query_ms .= '('.$key.', 0),';
					$query_u .= '("manufacturer_id='.$key.'", "'.$value.'"),';
				}
			}
			$query_ms = substr($query_ms, 0, -1);
			$mysqli->query($query_ms);
			$query_u = substr($query_u, 0, -1);
			$mysqli->query($query_u);
		}
		unset($query_u, $query_ms, $man_s_list, $key, $value, $manufacturer);
		echo "2";
		## Обработка опций
		if ($option) {
			$query_ov = 'INSERT INTO `bsc_option_value` (`option_id`, `sort_order`) VALUES ';
			foreach ($option as $opt) {
				$query_ov .= '(19, 0),';
			}
			$query_ov = substr($query_ov, 0, -1);
			$mysqli->query($query_ov);
			unset($query_ov, $opt);
			$query_ov = 'SELECT `option_value_id` FROM `bsc_option_value` WHERE `option_id` = 19';
			$result_ov = $mysqli->query($query_ov);
			$option_list = array();
			while ($row = $result_ov->fetch_assoc()) {
				$option_list[] = $row['option_value_id'];
			}
			unset($query_ov, $row, $result_ov);
			$query_ovd = 'SELECT `option_value_id` FROM `bsc_option_value_description` WHERE `option_id`=19';
			$result_ovd = $mysqli->query($query_ovd);
			while ($row = $result_ovd->fetch_assoc()) {
				$opt_ovd_list[$row['option_value_id']] = $row['option_value_id'];
			}
			unset($query_ovd, $result_ovd, $row);
			$query_ovd = 'INSERT INTO `bsc_option_value_description` (`option_value_id`, `language_id`, `option_id`, `name`) VALUES ';
			foreach ($option_list as $key => $value) {
				if (!(in_array($value, $opt_ovd_list))) {
					$query_ovd .= '('.$value.', 1, 19, "'.$option[$key].'"),';
				}
			}
			$query_ovd = substr($query_ovd, 0, -1);
			$mysqli->query($query_ovd);
		}
		unset($query_ovd, $key, $value, $option_list, $option);
		$query_ovd = 'SELECT `option_value_id`, `name` FROM `bsc_option_value_description` WHERE `option_id`=19';
		$result_ovd = $mysqli->query($query_ovd);
		$option_list = array();
		while ($row = $result_ovd->fetch_assoc()) {
			$option_list[$row['option_value_id']] = $row['name'];
		}
		unset($query_ovd, $result_ovd, $row);
		## Создаём запросы на добавление данных товаров, изображений, производителей и опций
		echo "3";
		foreach ($product_option as $key => $value) {
			$query_po = 'INSERT INTO `bsc_product_option` (`product_id`, `option_id`, `required`) VALUES ('.$key.', 19, 1)';
			$mysqli->query($query_po);
			$query_pov = 'INSERT INTO `bsc_product_option_value` (`product_option_id`, `product_id`, `option_id`, `option_value_id`, `quantity`, `subtract`) VALUES ';
			$i = $mysqli->insert_id;
			foreach ($value as $subvalue) {
				$x = array_search($subvalue, $option_list);
				if ($x !== False) {
					$query_pov .= '('.$i.', '.$key.', 19, '.$x.', 10, 1),';
				}
			}
			$query_pov = substr($query_pov, 0, -1);
			$mysqli->query($query_pov);
		}
		unset($product_option, $key, $value, $query_po, $query_pov, $i, $subvalue, $x, $option_list);
		/*
		$query_p = 'INSERT INTO `bsc_product` (`product_id`, `model`, `quantity`, `stock_status_id`, `image`, `manufacturer_id`, `shipping`, `price`, `start_price`, `minimum`, `status`) VALUES ';
		$query_pd = 'INSERT INTO `bsc_product_description` (`product_id`, `language_id`, `name`, `description`, `meta_title`, `meta_description`) VALUES ';
		$query_pl = 'INSERT INTO `bsc_product_to_layout` (`product_id`, `store_id`, `layout_id`) VALUES ';
		$query_pc = 'INSERT INTO `bsc_product_to_category` (`product_id`, `category_id`) VALUES ';
		$query_ps = 'INSERT INTO `bsc_product_to_store` (`product_id`, `store_id`) VALUES ';
		$query_i = 'INSERT INTO `bsc_product_image` (`product_id`, `image`) VALUES ';
		$query_u = 'INSERT INTO `bsc_url_alias` (`query`, `keyword`) VALUES ';
		*/
		$related = array();
		$cat_list = array();
		$result = $mysqli->query('SELECT `category_id`, `parent_id` FROM `bsc_category`');
		while ($row = $result->fetch_assoc()) {
			$cat_list[$row['category_id']] = $row['parent_id'];
		}
		foreach ($products as $product) {
			$man_id = array_search($product['manufacturer'], $man_list);
			## Запос на добавление продукта в таблицу bsc_product
			$query_product = 'INSERT INTO `bsc_product` (`product_id`, `model`, `quantity`, `stock_status_id`, `image`, `manufacturer_id`, `shipping`, `price`, `start_price`, `minimum`, `status`) VALUES ('.$product['product_id'].', '.$product['product_id'].', 10, 5, "'.$picture[$product['product_id']][0].'", '.$man_id.', 1, '.$product['price'].', '.$product['start_price'].', '.(int)$product['minimum_order_quantity'].', 1)';
			$mysqli->query($query_product);
			## Запос на добавление описания продукта в таблицу bsc_product_description
			$query_product_description = 'INSERT INTO `bsc_product_description` (`product_id`, `language_id`, `name`, `description`, `meta_title`, `meta_description`) VALUES ('.$product['product_id'].', 1, "'.$product['name'].'", "'.$product['description'].'", "'.$product['meta_title'].'", "'.$product['meta_description'].'")';
			$mysqli->query($query_product_description);
			## Запос на добавление шаблона продукта в таблицу bsc_product_to_layout
			$query_product_layout = 'INSERT INTO `bsc_product_to_layout` (`product_id`, `store_id`, `layout_id`) VALUES ('.$product['product_id'].', 0, 2)';
			$mysqli->query($query_product_layout);
			## Запос на добавление SEO-URL продукта в таблицу bsc_url_alias
			$query_url = 'INSERT INTO `bsc_url_alias` (`query`, `keyword`) VALUES ("product_id='.$product['product_id'].'", "'.$product['url'].'")';
			$mysqli->query($query_url);
			## Запос на добавление связи продукта и категории в таблицу bsc_product_to_category
			$query_product_category = 'INSERT INTO `bsc_product_to_category` (`product_id`, `category_id`) VALUES ('.$product['product_id'].', '.$product['category_id'].'),';
			$query_product_category .= '('.$product['product_id'].', '.$cat_list[$product['category_id']].'),';
			$query_product_category .= '('.$product['product_id'].', '.$cat_list[$cat_list[$product['category_id']]].')';
			$mysqli->query($query_product_category);
			## Запрос на добавление магазина продукта в таблицу bsc_product_to_store
			$query_product_store = 'INSERT INTO `bsc_product_to_store` (`product_id`, `store_id`) VALUES ('.$product['product_id'].', 0)';
			$mysqli->query($query_product_store);
			## Запрос на добавление связей изображений и продукта в таблице bsc_product_image
			$query_product_image = 'INSERT INTO `bsc_product_image` (`product_id`, `image`) VALUES ';
			foreach ($picture[$product['product_id']] as $pict) {
				$query_product_image .= '('.$product['product_id'].', "'.$pict.'"),';
			}
			$query_product_image = substr($query_product_image, 0, -1);
			$mysqli->query($query_product_image);

			/*
			## Обработка алиасов
			$query_u .= '("product_id='.$product['product_id'].'", "'.$product['url'].'"),';
			## Обработка продуктов
			$query_p .= '('.$product['product_id'].', '.$product['product_id'].', 10, 5, "'.$picture[$product['product_id']][0].'", '.$man_id.', 1, '.$product['price'].', '.$product['start_price'].', 1, 1),';
			## Обработка описания продуктов
			$query_pd .= '('.$product['product_id'].', 1, "'.$product['name'].'", "'.$product['description'].'", "'.$product['meta_title'].'", "'.$product['meta_description'].'"),';
			## Обработка шаблона продуктов
			$query_pl .= '('.$product['product_id'].', 0, 2),';
			## Обработка категорий продуктов
			$query_pc .= '('.$product['product_id'].', '.$product['category_id'].'),';
			## Обработка магазина продуктов
			$query_ps .= '('.$product['product_id'].', 0),';
			*/
			## Обработка рекомендаций продуктов
			$rels = '';
			$query_relation = 'INSERT INTO `bsc_product_related` (`product_id`, `related_id`) VALUES ';
			foreach ($products as $related_product) {
				if ($product['name'] == $related_product['name'] and ($product['product_id'] != $related_product['product_id'])) {
					$rels .= '('.$product['product_id'].', '.$related_product['product_id'].'),';
				}
			}
			if ($rels != '') {
				$query_relation .= $rels;
				$query_relation = substr($query_relation, 0, -1);
				$mysqli->query($query_relation);
			}
		}
		unset($products, $product, $pict, $picture, $man_list, $man_id, $rels, $related_product);
		/*$query_r = 'INSERT INTO `bsc_product_related` (`product_id`, `related_id`) VALUES ';
		foreach ($related as $key => $value) {
			foreach ($value as $subvalue) {
				$query_r .= '('.$key.', '.$subvalue.'),';
			}
		}
		$query_r = substr($query_r, 0, -1);
		$mysqli->query($query_r);*/
		unset($related, $key, $value, $subvalue);
		
		/*
		$query_p = substr($query_p, 0, -1);
		$mysqli->query($query_p);
		$query_u = substr($query_u, 0, -1);
		$mysqli->query($query_u);
		$query_pd = substr($query_pd, 0, -1);
		$mysqli->query($query_pd);
		$query_pl = substr($query_pl, 0, -1);
		$mysqli->query($query_pl);
		$query_pc = substr($query_pc, 0, -1);
		$mysqli->query($query_pc);
		$query_ps = substr($query_ps, 0, -1);
		$mysqli->query($query_ps);
		$query_i = substr($query_i, 0, -1);
		$mysqli->query($query_i);
		*/

		echo 'm: '.memory_get_usage(); - $mem_start;
	}
?>