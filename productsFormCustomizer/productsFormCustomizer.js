var ProductsFormCustomizer = BX.namespace('ProductsFormCustomizer');

ProductsFormCustomizer.initCustomization = function() {
	BX.ready(function () {
		ProductsFormCustomizer.eventsCollecting();
	});

	const listOfEvents = [
		"Grid::enabled",						// при добавлении товара
		"Grid::updated",						// при обновлении грида Товаров
		"onEntityDetailsTabShow",				// при переходе на вкладку Товары
	];

	listOfEvents.forEach((currentEvent) => {
		BX.addCustomEvent(
			currentEvent,
			BX.delegate(function(data) {
				ProductsFormCustomizer.getProductsEditableness();
			})
		);
	});

	const listOfOrderSaveEvents = [
		"onCrmEntityUpdate",
		"onCrmEntityCreate"						// при сохранении предложения(заказа)
	];

	listOfOrderSaveEvents.forEach((currentSaveEvent) => {
		BX.addCustomEvent(
			currentSaveEvent,
			BX.delegate(function(data) {
				ProductsFormCustomizer.orderUpdatingHandler(data);
			})
		);
	});

}

// события
ProductsFormCustomizer.eventsCollecting = function() {
	let originalBxOnCustomEvent = BX.onCustomEvent;

	BX.onCustomEvent = function (eventObject, eventName, eventParams, secureParams)
	{
		// onMenuItemHover например выбрасывает в другом порядке
		let realEventName = BX.type.isString(eventName) ?
			eventName : BX.type.isString(eventObject) ? eventObject : null;

		if (realEventName) {
			console.log(
				'%c' + realEventName,
				'background: #222; color: #bada55; font-weight: bold; padding: 3px 4px;'
			);
		}

		console.dir({
			eventObject: eventObject,
			eventParams: eventParams,
			secureParams: secureParams
		});

		originalBxOnCustomEvent.apply(
			null, arguments
		);
	};
}

// метод делает ajax запрос, получает все id товаров, в grid запрещает к редактированию тех, для которых это редактирование запрещено
ProductsFormCustomizer.getProductsEditableness = function() {
	let data = {};
	data['action'] = 'checkeditability';
	BX.ajax({
		url:"/local/assets/js/productsFormCustomizer/productsFormCustomizer.php",
		data:data,
		dataType:'json',
		method:"POST",
		onsuccess: ( data ) => {
			BX.closeWait();

			if( BX.Main.gridManager.getById("CCrmEntityProductListComponent") !== null ) {
				let currentRows = BX.Main.gridManager.getById("CCrmEntityProductListComponent").instance.getRows().rows;
				for( let i=0; i<currentRows.length; i++ ) {
					// строка, которая содержит в себе товары имеет класс <<main-grid-row-edit>>
					if( currentRows[i].node.classList.contains("main-grid-row-edit") ) {

						// получим идентификатор товара из ссылки
						if( currentRows[i].node.querySelector(".ui-ctl-icon-forward") !== null ) {
							let linkToProduct = currentRows[i].node.querySelector(".ui-ctl-icon-forward").attributes.href.value;
							ProductsFormCustomizer.getProductIdFromLink( linkToProduct, data, currentRows[i].node );
						}

						// установим обработчик поля Скидки PRICE_DISCOUNT
						ProductsFormCustomizer.doNotLetInputNegativeDiscount(currentRows[i].node);
					}


				}
			}

		},
		onfailure:function(error){
			alert('При ajax запросе произошла ошибка');
		}
	});
}

// метод не позволяет вносить отрицательную скидку в поле скидка
ProductsFormCustomizer.doNotLetInputNegativeDiscount = function(rowObj) {
	let allInputsOfThisRow = rowObj.querySelectorAll('input');
	if( allInputsOfThisRow !== null ) {
		for( let j=0; j<allInputsOfThisRow.length; j++ ) {
			if( allInputsOfThisRow[j].parentNode.dataset.name === "DISCOUNT_PRICE" ) {
				BX.adjust(
					BX(allInputsOfThisRow[j]),
					{
						events: {
							change: function() { if(this.value < 0 ) {this.value = 0} }
						}
					}
				);
			}
		}
	}
}

// метод получает идентификатор товара из ссылки устанавливает запрет на редактирование, если нужно, создает title для строки и data-alloweddiscount
ProductsFormCustomizer.getProductIdFromLink = function( linkToProduct, data, currentNoda ) {
	if( linkToProduct.match(/crm\/catalog\/[0-9]+\/product\/[0-9]+/) ) {
		let shortProductLink = (linkToProduct.match(/\/product\/[0-9]+/))[0];
		if( shortProductLink.length && shortProductLink.match(/[0-9]+/) ) {
			let productId = (shortProductLink.match(/[0-9]+/))[0];

			if( data[productId]['EDITING_PRICE_VALUE'] == null ) {
				BX.addClass( currentNoda, "main-grid-row-edit_canceled" );
			}

			if( data[productId]['ALLOWED_DISCOUNT_VALUE'] !== null ) {
				let titleText = "Допустимая скидка для этого товара - ";
				titleText += data[productId]['ALLOWED_DISCOUNT_VALUE'] + "%\n";
				titleText += 'Вы можете указать большую скидку,\nоднако заказ уйдет на согласование';

				BX.adjust(
					currentNoda,
					{
						props: {
							title: titleText
						},
						attrs: {
							'data-AllowedDiscount': data[productId]['ALLOWED_DISCOUNT_VALUE']
						}
					}
				);
			}
		}
	}
}

// метод позволяет запустить бизнес-процесс согласования сделки, если на какой-либо из товаров установлена выше допустимой скидка при сохранении сделки
ProductsFormCustomizer.orderUpdatingHandler = function( data ) {

		let someOfProductsDiscountExceeds = false;
		let orderId = data.entityId;

		if( BX.Main.gridManager.getById("CCrmEntityProductListComponent") !== null ) {
			// получим строки таблицы товаров
			let currentRows = BX.Main.gridManager.getById("CCrmEntityProductListComponent").instance.getRows().rows;
			if(currentRows.length > 0) {
				for( let i=0; i<currentRows.length; i++ ) {
					// строка, которая содержит в себе товары имеет класс <<main-grid-row-edit>>
					if( currentRows[i].node.classList.contains("main-grid-row-edit") ) {
						// у нас есть alloweddiscount
						// у нас есть заполненное значение скидки в data-name='DISCOUNT_PRICE'
						// сравниваем эти значения если величина установленной скидки менеджером превышает допустимую хотя бы для одного товара, то делаем ajax запрос
						if( BX(currentRows[i].node).dataset.alloweddiscount !== undefined ) {
							let currentAllowedDiscount = BX( currentRows[i].node ).dataset.alloweddiscount;
							let inputsOfCurrentRow = BX( currentRows[i].node ).querySelectorAll('input');
							inputsOfCurrentRow.forEach((singleInput) => {
								if( singleInput.parentNode.dataset.name === "DISCOUNT_PRICE" ) {
									let filledInputValue = singleInput.value;
									if( singleInput.value > currentAllowedDiscount ) {
										someOfProductsDiscountExceeds = true;
									}
								}
							});
						}
					}
				}
			}



			// если someOfProductsDiscountExceeds === true значит есть товары с превышенной скидкой делаем ajax запрос и запускаем БП
			if(someOfProductsDiscountExceeds) {
				let data = {};
				let template_id = 485;
				let sessid = BX.message("bitrix_sessid");
				let site = "s1";
				let ajax_action = "start_workflow";
				let module_id = "crm";
				let entity = 'Bitrix\\Crm\\Integration\\BizProc\\Document\\Quote';
				let document_type = "QUOTE";
				let document_id = "QUOTE_" + orderId;
				let url = "/bitrix/components/bitrix/bizproc.workflow.start/ajax.php";

				data['template_id'] = template_id;
				data['sessid'] = sessid;
				data['site'] = site;
				data['ajax_action'] = ajax_action;
				data['module_id'] =	module_id;
				data['entity'] = entity;
				data['document_type'] = document_type;
				data['document_id'] = document_id;
				data['url'] = url;

				debugger;

				BX.ajax({
					url:url,
					data:data,
					dataType:'json',
					method:"POST",
					onsuccess:function(data){
						BX.closeWait();
						debugger;
						// создадим уникальный идентификатор popup
						let popupWindowIdentifier = "popup-message_" + (Math.random() + 1).toString(36).substring(7);
						// инициализируем popup
						let popup = BX.PopupWindowManager.create(popupWindowIdentifier, null, {
							autoHide: true,
							offsetTop: 0,
							padding: 60,
							overlay : false,
							draggable: {restrict:true},
							closeByEsc: true,
							content: "Запущен Бизнес-процесс на согласование скидок на товары!",
							offsetLeft: 0,
							closeIcon: { right : "0", top : "0", width: "64px", height: "64px", opacity: 1},
							events: {
								onPopupShow: function() {

								},
								onPopupClose: function() {
									BX.PopupWindowManager.getCurrentPopup().destroy();
								}
							}
						});
						// отобразим popup
						popup.show();
					},
					onfailure:function(error){
						alert('При попытке создать Бизнес-процесс произошла ошибка');
					}
				});

			}




		}


	BX.closeWait();
}
