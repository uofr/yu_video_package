M.local_kaltura = {};

M.local_kaltura.loading_panel = {};

M.local_kaltura.show_loading = function () {
    M.local_kaltura.loading_panel = new Y.YUI2.widget.Panel("wait",
        {width:"240px", fixedcenter:true, close:false, draggable:false, zindex:4, modal:true, visible:false});

    M.local_kaltura.loading_panel.setHeader("Loading, please wait...");
    M.local_kaltura.loading_panel.setBody('<img src="/moodle/local/kaltura/pix/rel_interstitial_loading.gif" />');
    M.local_kaltura.loading_panel.render();

    M.local_kaltura.loading_panel.show();
};

M.local_kaltura.hide_loading = function () {
    M.local_kaltura.loading_panel.hide();
};

M.local_kaltura.dataroot = {};

M.local_kaltura.set_dataroot = function(web_location) {
    M.local_kaltura.dataroot = web_location;
};

M.local_kaltura.get_thumbnail_url = function(entry_id) {

    YUI().use("io-base", "json-parse", "node", function (Y) {
        var location = M.local_kaltura.dataroot + entry_id;

        Y.io(location);

        function check_conversion_status(id, o) {
            if ('' != o.responseText) {

                var data = Y.JSON.parse(o.responseText);

                var img_tag = Y.one("#media_thumbnail");

                if (data.thumbnailUrl != img_tag.get("src")) {
                    img_tag.set("src", data.thumbnailUrl);
                    img_tag.set("alt", data.name);
                    img_tag.set("title", data.name);
                }
            }
        }

        Y.on('io:complete', check_conversion_status, Y);

    });

};

/*
 * Perform course searching with auto-complete
 */
M.local_kaltura.search_course = function() {

    YUI({filter: 'raw'}).use("autocomplete", function(Y) {
        var search_txt = Y.one('#kaltura_search_txt');
        var kaltura_search = document.getElementById("kaltura_search_txt");
        var search_btn = Y.one('#kaltura_search_btn');
        var clear_btn = Y.one('#kaltura_clear_btn');

        search_txt.plug(Y.Plugin.AutoComplete, {
            resultTextLocator: 'fullname',
            enableCache: false,
            minQueryLength: 2,
            resultListLocator: 'data.courses',
            resultFormatter: function (query, results) {
                return Y.Array.map(results, function(result) {
                    var course = result.raw;
                    if (course.shortname) {
                        return course.fullname + " (" + course.shortname + ")";
                    }
                    return course.fullname;
                });
            },
            source: 'courses.php?query={query}&action=autocomplete',
            on : {
                select : function(e) {
                    Y.io('courses.php', {
                        method: 'POST',
                        data: {course_id : e.result.raw.id, action: 'select_course'},
                        on: {
                            success: function(id, result) {
                                var data = Y.JSON.parse(result.responseText);
                                if (data.failure && data.failure == true) {
                                    alert(data.message);
                                } else {
                                    document.getElementById('resourceobject').src = decodeURIComponent(data.url);
                                }
                            }
                        }
                    });
                }
            }
        });

        kaltura_search.onkeypress = function(e) {
            // Enter is pressed.
            if (e.keyCode === 13) {
                var query = search_txt.get('value');
                // Don't accept an empty search string.
                if (!(/^\s*$/.test(query))) {
                    document.getElementById('resourceobject').src = 'courses.php?action=search&query=' + query;
                    // Lose focus of the auto-suggest menu.
                    kaltura_search.blur();
                }
            }
        };

        search_btn.on('click', function(e) {
            var query = search_txt.get('value');
            // Don't accept an empty search string.
            if (!(/^\s*$/.test(query))) {
                document.getElementById('resourceobject').src = 'courses.php?action=search&query=' + query;
                kaltura_search.blur();
            }
        });

        clear_btn.on('click', function(e) {
            search_txt.set("value", "");
        });

    });

};

M.local_kaltura.init_config = function (Y, test_script) {

    // Check for an instance of the Kaltura connection type element.
    if (Y.DOM.byId("id_s_local_kaltura_conn_server")) {

        // Retrieve the connection type Node.
        var connection_type = Y.one('#id_s_local_kaltura_conn_server');

        // Check for the selected option.
        var connection_type_dom = Y.Node.getDOMNode(connection_type);

        // Check if the first option is selected.
        if (0 == connection_type_dom.selectedIndex) {

            // Disable the URI setting.
            Y.DOM.byId("id_s_local_kaltura_uri").disabled = true;
        } else {
            // Enable the URI setting.
            Y.DOM.byId("id_s_local_kaltura_uri").disabled = false;
        }

        // Add 'change' event to the connection type selection drop down.
        connection_type.on('change', function (e) {

            var connection_uri = Y.DOM.byId("id_s_local_kaltura_uri");

            if (connection_uri.disabled) {
                connection_uri.disabled = false;
            } else {
                connection_uri.disabled = true;
            }
        });

        // Add a 'change' event to the Kaltura player selection drop down.
        var kaltura_player = Y.one('#id_s_local_kaltura_player');

        // Check for the selected option.
        var kaltura_player_dom = Y.Node.getDOMNode(kaltura_player);

        var length = kaltura_player_dom.length - 1;

        if (length == kaltura_player_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_player_custom').disabled = false;
        } else {
            Y.DOM.byId('id_s_local_kaltura_player_custom').disabled = true;
        }

        kaltura_player.on('change', function (e) {

            var kaltura_custom_player = Y.DOM.byId("id_s_local_kaltura_player_custom");

            var kaltura_player_dom = Y.Node.getDOMNode(e.target);

            var length = kaltura_player_dom.length - 1;

            if (length == kaltura_player_dom.selectedIndex) {
                kaltura_custom_player.disabled = false;
            } else {
                kaltura_custom_player.disabled = true;
            }

        });

        // Add a 'change' event to the Kaltura resource player selection drop down.
        var kaltura_player_resource = Y.one('#id_s_local_kaltura_player_resource');

        // Check for the selected option.
        var kaltura_player_resource_dom = Y.Node.getDOMNode(kaltura_player_resource);

        length = kaltura_player_resource_dom.length - 1;

        if (length == kaltura_player_resource_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_player_resource_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_player_resource_custom').disabled = true;
        }

        kaltura_player_resource.on('change', function (e) {

            var kaltura_custom_player_resource = Y.DOM.byId("id_s_local_kaltura_player_resource_custom");

            var kaltura_player_resource_dom = Y.Node.getDOMNode(e.target);

            var length = kaltura_player_resource_dom.length - 1;

            if (length == kaltura_player_resource_dom.selectedIndex) {
                kaltura_custom_player_resource.disabled = false;
            } else {
                kaltura_custom_player_resource.disabled = true;
            }

        });

    }

};