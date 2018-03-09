var fastery = fst = {};

fastery.init = function (e) {

    fst.settings = {
        city: 'fastery_customer_main_city',
        cityContainer: 'cityBlock',
        map: null,
        balloonElement: null,
        openPointId: null,
        collection: {
            'terminal': null,
            'pvz': null
        }
    };

    // Получим значние нажатой клавиши
    this.getChar = function (e) {
        if (e.which == null) { // IE
            if (e.keyCode < 32) return null;
            return String.fromCharCode(e.keyCode)
        }

        if (e.which != 0 && e.charCode != 0) {
            if (e.which < 32) return null;
            return String.fromCharCode(e.which);
        }

        return null;
    };

    // Ваставка ноды после узла
    this.insertAfter = function (parent, node, referenceNode) {
        parent.insertBefore(node, referenceNode.nextSibling);
    };

    // ajax http запрос
    this.request = function (url, params, method) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, url + params, false);
        xhr.send();
        if (xhr.readyState == 4) {
            return xhr.responseText;
        }
        return false;
    };

    // Закрытие карты
    function closeMap() {
        var map = document.getElementById('fastery-map');
        var overlay = document.getElementById('fastery-overlay');
        map.parentNode.removeChild(overlay);
        map.parentNode.removeChild(map);
        fst.settings.map.destroy();
        fst.settings.map = null;
    }

    // Выбор пункта на карте
    this.choosePoint = function (type, point_id) {

        var selectId = 'fastery.' + type;
        var options = document.getElementById(selectId).getElementsByTagName('option');

        for (var i = 0; i < options.length; i++) {
            options.item(i).removeAttribute('selected');
            if (options.item(i).getAttribute('data-point_id') == point_id) {
                options.item(i).setAttribute('selected', 'selected');
                document.getElementById(type + '-point_id').value = point_id;
            }
        }

        setTimeout(function () {
            var event = new Event("change");
            document.getElementById(selectId).dispatchEvent(event);
        }, 200);

        if (typeof BX.Sale != "undefined" && typeof BX.Sale.OrderAjaxComponent == 'object') {
            BX.Sale.OrderAjaxComponent.sendRequest();
        }

        closeMap();
    };

    // Выбор пункта через выпадающий список
    this.selectChoose = function () {
        var options = this.getElementsByTagName('option');

        for (var i = 0; i < options.length; i++) {
            if (options.item(i).getAttribute('data-point_address') == this.value) {
                options.item(i).setAttribute('selected', true);
                document.getElementById(this.name + '-point_id').value = options.item(i).getAttribute('data-point_id');
            }
        }

        if (typeof BX.Sale != "undefined" && typeof BX.Sale.OrderAjaxComponent == 'object') {
            BX.Sale.OrderAjaxComponent.sendRequest();
        }
    };

    // Генерация карты
    this.createMap = function () {


        var pickupType = this.getAttribute('rel');
        var selectId = 'fastery.' + pickupType;
        var options = document.getElementById(selectId).getElementsByTagName('option');

        // Если нет контейнера с картой то нужно его создать
        if (!document.getElementById('fastery-map')) {

            var html = document.createElement('div');
            var overlay = document.createElement('div');
            var close = document.createElement('a');
            close.className = 'fastery-map-close';
            close.textContent = "\u00D7";

            html.appendChild(close);
            html.id = 'fastery-map';
            overlay.id = 'fastery-overlay';

            close.addEventListener('click', closeMap, false);
            overlay.addEventListener('click', closeMap, false);

            document.body.appendChild(html);
            document.body.appendChild(overlay);

            var startPrice = parseInt(document.getElementById(selectId).getAttribute('data-min_price'));
            var endPrice = parseInt(document.getElementById(selectId).getAttribute('data-max_price'));
            if (startPrice < endPrice) {

                var filter = document.createElement('div');
                var sliderPrice = document.createElement('div');
                var sliderDates = document.createElement('div');
                var minPrice = document.createElement('div');
                var maxPrice = document.createElement('div');

                filter.id = 'fastery-map-filter';
                minPrice.id = 'fastery-min-price-filter';
                minPrice.textContent = document.getElementById(selectId).getAttribute('data-min_price') + ' р.';
                maxPrice.id = 'fastery-max-price-filter';
                maxPrice.textContent = document.getElementById(selectId).getAttribute('data-max_price') + ' р.';

                sliderPrice.id = 'fastery-slider-price';
                sliderDates.id = 'fastery-slider-date';
                filter.appendChild(minPrice);
                filter.appendChild(sliderPrice);
                filter.appendChild(maxPrice);
                html.appendChild(filter);

                noUiSlider.create(sliderPrice, {
                    start: [
                        parseInt(document.getElementById(selectId).getAttribute('data-min_price')),
                        parseInt(document.getElementById(selectId).getAttribute('data-max_price'))
                    ],
                    connect: true,
                    step: 1,
                    range: {
                        'min': parseInt(document.getElementById(selectId).getAttribute('data-min_price')),
                        'max': parseInt(document.getElementById(selectId).getAttribute('data-max_price'))
                    }
                });

                var snapValues = [
                    document.getElementById('fastery-min-price-filter'),
                    document.getElementById('fastery-max-price-filter')
                ];

                sliderPrice.noUiSlider.on('update', function (values, handle) {
                    snapValues[handle].innerHTML = parseInt(values[handle]) + ' р.';
                });

                // После обновления переопределим точки на карте
                sliderPrice.noUiSlider.on('change', function (values, handle) {
                    geoObjects = [];
                    clusterer.removeAll();
                    fst.settings.map.geoObjects.removeAll();

                    var j = 0;
                    for (var i = 0; i < options.length; i++) {
                        var option = options.item(i);
                        var cost = parseInt(option.getAttribute('data-cost'));
                        var minCost = parseInt(values[0]);
                        var maxCost = parseInt(values[1]);

                        if (cost >= minCost && cost <= maxCost) {
                            geoObjects[j] = new ymaps.Placemark(
                                [
                                    option.getAttribute('data-lat'),
                                    option.getAttribute('data-lng')
                                ],
                                {
                                    iconContent: '<span class="fastery-icon-label">' + (i + 1) + '</span>',
                                    balloonContentHeader: '<span class="fastery-point" style="background-image: url(/bitrix/modules/fastery.shipping/asset/label-' + pickupType + '.png)";>' + (i + 1) + '</span><br />' + option.getAttribute('data-point_address'),
                                    balloonContentBody: '<div><div class="fastery-content-body"><p>Телефон: ' + option.getAttribute('data-phone') + '</p><p>Цена: ' + option.getAttribute('data-cost') + ' руб.</p></div><div class="fastery-line"></div><p><a class="fastery-choose-point" onclick="fst.choosePoint(\'' + pickupType + '\',\'' + option.getAttribute('data-point_id') + '\')">Выбрать</a></p></div>',
                                    id: option.getAttribute('data-point_id'),
                                    type: pickupType,
                                    cost: option.getAttribute('data-cost'),
                                    phone: option.getAttribute('data-phone'),
                                    minTerm: option.getAttribute('data-min_term'),
                                    maxTerm: option.getAttribute('data-max_term'),
                                    address: option.getAttribute('data-point_address'),
                                    hintContent: option.getAttribute('data-point_address')
                                },
                                {
                                    balloonShadow: false,
                                    balloonLayout: balloonLayout,
                                    balloonPanelMaxMapArea: 0,
                                    iconLayout: 'default#imageWithContent',
                                    iconImageHref: '/bitrix/modules/fastery.shipping/asset/label-' + pickupType + '.png',
                                    iconImageSize: [57, 60],
                                    iconImageOffset: [-28, -60]
                                }
                            );
                            geoObjects[j].events.add('click', function (e) {
                                var coords = e.get('coords');
                                fst.settings.map.setCenter(coords);
                            });
                            j++;
                        }
                    }
                    if (geoObjects.length) {
                        clusterer.add(geoObjects);
                        fst.settings.map.geoObjects.add(clusterer);
                    }
                });
            }
        }

        if (!fst.settings.map) {
            fst.settings.map = new ymaps.Map('fastery-map', {
                center: [
                    options.item(i).getAttribute('data-lat'),
                    options.item(i).getAttribute('data-lng')
                ],
                controls: [],
                zoom: 9
            });

            balloonLayout = ymaps.templateLayoutFactory.createClass(
                '<div class="fastery-balloon">' +
                '<a class="fastery-balloon-close" href="#">&times;</a>' +
                '<h6>$[properties.balloonContentHeader]</h6>' +
                '<p class="text-cost">$[properties.balloonContentBody]</p>' +
                '</div>', {
                    build: function () {
                        this.constructor.superclass.build.call(this);
                        fst.settings.balloonElement = this;
                        document.getElementsByClassName('fastery-balloon-close').item(0).addEventListener('click', function (e) {
                            fst.settings.balloonElement.events.fire('userclose');
                            e.preventDefault();
                        });
                    },
                    clear: function () {
                        fst.settings.balloonElement = null;
                        this.constructor.superclass.clear.call(this);
                    }
                }
            );

            var zoomControl = new ymaps.control.ZoomControl({
                options: {
                    size: "small",
                    position: {top: 230, left: 10}
                }
            });
            fst.settings.map.controls.add(zoomControl);

            if (fst.settings.collection == null || fst.settings.collection[pickupType] === null) {
                fst.settings.collection[pickupType] = new ymaps.GeoObjectCollection();
            }

            var clusterColor = 'islands#invertedRedClusterIcons';
            if (pickupType == 'pvz') clusterColor = 'islands#invertedBlueClusterIcons';

            clusterer = new ymaps.Clusterer({
                preset: clusterColor,
                groupByCoordinates: false,
                clusterDisableClickZoom: false,
                clusterHideIconOnBalloonOpen: false,
                geoObjectHideIconOnBalloonOpen: false
            });

            geoObjects = [];

            for (var i = 0; i < options.length; i++) {

                if (options.item(i).getAttribute('data-point_id') == document.getElementById(pickupType + '-point_id').value) {
                    fst.settings.openPointId = i;
                    fst.settings.map.setZoom(15);
                    fst.settings.map.setCenter([options.item(i).getAttribute('data-lat'), options.item(i).getAttribute('data-lng')]);
                }

                geoObjects[i] = new ymaps.Placemark(
                    [
                        options.item(i).getAttribute('data-lat'),
                        options.item(i).getAttribute('data-lng')
                    ],
                    {
                        iconContent: '<span class="fastery-icon-label">' + (i + 1) + '</span>',
                        balloonContentHeader: '<span class="fastery-point" style="background-image: url(/bitrix/modules/fastery.shipping/asset/label-' + pickupType + '.png)";>' + (i + 1) + '</span><br />' + options.item(i).getAttribute('data-point_address'),
                        balloonContentBody: '<div><div class="fastery-content-body"><p>Телефон: ' + options.item(i).getAttribute('data-phone') + '</p><p>Цена: ' + options.item(i).getAttribute('data-cost') + ' руб.</p></div><div class="fastery-line"></div><p><a class="fastery-choose-point" onclick="fst.choosePoint(\'' + pickupType + '\',\'' + options.item(i).getAttribute('data-point_id') + '\')">Выбрать</a></p></div>',
                        id: options.item(i).getAttribute('data-point_id'),
                        type: pickupType,
                        cost: options.item(i).getAttribute('data-cost'),
                        phone: options.item(i).getAttribute('data-phone'),
                        minTerm: options.item(i).getAttribute('data-min_term'),
                        maxTerm: options.item(i).getAttribute('data-max_term'),
                        address: options.item(i).getAttribute('data-point_address'),
                        hintContent: options.item(i).getAttribute('data-point_address'),
                        balloonContent: '<div style = "margin-top: 30px; margin-left: 20px;" ><b>Оперный театр</b><br/>ул. Белинского, 59</div>',
                        balloonHeader: 'Заголовок балуна'
                    },
                    {
                        balloonShadow: false,
                        balloonLayout: balloonLayout,
                        balloonPanelMaxMapArea: 0,
                        iconLayout: 'default#imageWithContent',
                        iconImageHref: '/bitrix/modules/fastery.shipping/asset/label-' + pickupType + '.png',
                        iconImageSize: [57, 60],
                        iconImageOffset: [-28, -60]
                    }
                );

                geoObjects[i].events.add('click', function (e) {
                    var coords = e.get('coords');
                    fst.settings.map.setCenter(coords);
                });
            }

            clusterer.add(geoObjects);
            fst.settings.map.geoObjects.add(clusterer);

            if (fst.settings.openPointId != null) {
                var objectState = clusterer.getObjectState(geoObjects[fst.settings.openPointId]);
                if (objectState.isClustered) {
                    objectState.cluster.state.set('activeObject', geoObjects[fst.settings.openPointId]);
                    clusterer.balloon.open(objectState.cluster);
                } else if (objectState.isShown) {
                    geoObjects[fst.settings.openPointId].balloon.open();
                }
            }
        }
        else {
            fst.settings.map.destroy();// Деструктор карты
            fst.settings.map = null;
        }

    }

    watch = function (e) {

        var links = document.getElementsByClassName('choise_on_map');
        for (var i = 0; i < links.length; i++) {
            links.item(i).addEventListener("click", fst.createMap, false);
        }

        var selects = document.getElementsByClassName('fastery-select');
        for (var i = 0; i < selects.length; i++) {
            selects.item(i).addEventListener("change", fst.selectChoose, false);
        }

    };

    watch();
};

window.onload = function (e) {
    fst.init();
};