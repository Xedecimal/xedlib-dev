<?php

session_start();

require_once('h_main.php');
require_once('lib/h_template.php');
require_once('lib/a_file.php');
require_once('lib/a_log.php');

$ca = GetVar('ca');

$dsUser = new DataSet($db, 'user');
$lm = new LoginManager();
$lm->AddDataset($dsUser, 'usr_pass', 'usr_name');
$dsLogs = new DataSet($db, 'docmanlog');
$logger = new LoggerAuth($dsLogs, $dsUser);

if (!$user = $lm->Prepare($ca))
{
	die($lm->Get($me));
}

$fm_actions = array(
	FM_ACTION_UNKNOWN => 'Unknown',
	FM_ACTION_CREATE  => 'Created',
	FM_ACTION_DELETE  => 'Deleted',
	FM_ACTION_REORDER => 'Reordered',
	FM_ACTION_RENAME  => 'Renamed',
	FM_ACTION_UPDATE  => 'Updated',
	FM_ACTION_UPLOAD  => 'Uploaded',
);

if ($ca == 'login') $logger->Log($user['usr_id'], 'Logged in', '');

function fm_watcher($action, $target)
{
	global $user, $logger, $fm_actions;
	$logger->Log($user['usr_id'], $fm_actions[$action], $target);
}

$page_title = 'File Administration Demo';
$page_head = '<script type="text/javascript" src="lib/js/swfobject.js"></script>';

$ca = GetVar('ca');

$fm = new FileManager('fman', 'test', array('Default', 'Gallery'));
$fm->Behavior->Recycle = true;
$fm->Behavior->ShowAllFiles = true;
$fm->Behavior->Watcher = array('fm_watcher');

$fm->Behavior->AllowAll();
$fm->Prepare($ca);

$page_body = "<p><a href=\"{$me}?ca=logout\">Log out</a></p>";
$page_body .= $fm->Get($me, $ca);

$logger->TrimByCount(5);
$page_body .= $logger->Get(10);

$context = null;
$files = count($fm->files['files']);
$dirs = count($fm->files['dirs']);
$page_body .= <<<EOF
	<script type="text/javascript">var dirs = "{$dirs}";</script>
	<script type="text/javascript">var files = "{$files}";</script>
EOF;

$page_body .= <<<EOF
<script type="text/javascript" src="lib/js/yui/yahoo-min.js" ></script>
<script type="text/javascript" src="lib/js/yui/event-min.js" ></script>
<script type="text/javascript" src="lib/js/yui/dom-min.js"></script>
<script type="text/javascript" src="lib/js/yui/animation-min.js"></script>
<script type="text/javascript" src="lib/js/yui/dragdrop-min.js" ></script>

<style type="text/css">
div.workarea { padding: 10px; float: left }

ul.draglist
{
    position: relative;
    width: 200px; 
    /*height: 300px;*/
    background: #f7f7f7;
    border: 1px solid gray;
    list-style: none;
    margin:0;
    padding:0;
}

ul.draglist li { margin: 1px; cursor: move; }

ul.draglist_alt
{
    position: relative;
    width: 200px; 
    list-style: none;
    margin:0;
    padding:0;
    /*
       The bottom padding provides the cushion that makes the empty 
       list targetable.  Alternatively, we could leave the padding 
       off by default, adding it when we detect that the list is empty.
    */
    padding-bottom:20px;
}

ul.draglist_alt li { margin: 1px; cursor: move; }
li.dirs_item { background-color: #D1E6EC; border:1px solid #7EA6B2; }
li.files_item { background-color: #FFCCCC; border:1px solid #7EA6B2; }
</style>

<script type="text/javascript">

(function() {

var Dom = YAHOO.util.Dom;
var Event = YAHOO.util.Event;
var DDM = YAHOO.util.DragDropMgr;

YAHOO.example.DDApp =
{
	init: function()
	{
		var rows = 3,cols = 2, i, j;
		new YAHOO.util.DDTarget("ul_dirs", "dirs");
		new YAHOO.util.DDTarget("ul_files", "files");

		for (j = 0; j < dirs; j = j + 1)
		{
			new YAHOO.example.DDList("dirs_" + j, 'dirs');
        }
		for (j = 0; j < files; j = j + 1)
		{
			new YAHOO.example.DDList("files_" + j, 'files');
        }

        Event.on("showButton", "click", this.showOrder);
        Event.on("switchButton", "click", this.switchStyles);
    },

    showOrder: function()
    {
        var parseList = function(ul, title)
        {
            var items = ul.getElementsByTagName("li");
            var out = title + ": ";
            for (i=0;i<items.length;i=i+1)
            {
                out += items[i].id + " ";
            }
            return out;
        };
    },

    switchStyles: function() {
        Dom.get("ul1").className = "draglist_alt";
        Dom.get("ul2").className = "draglist_alt";
    }
};

YAHOO.example.DDList = function(id, sGroup, config)
{
    YAHOO.example.DDList.superclass.constructor.call(this, id, sGroup, config);

    this.logger = this.logger || YAHOO;
    var el = this.getDragEl();
    Dom.setStyle(el, "opacity", 0.67); // The proxy is slightly transparent

    this.goingUp = false;
    this.lastY = 0;
};

YAHOO.extend(YAHOO.example.DDList, YAHOO.util.DDProxy,
{
    startDrag: function(x, y)
	{
        this.logger.log(this.id + " startDrag");

        // make the proxy look like the source element
        var dragEl = this.getDragEl();
        var clickEl = this.getEl();
        Dom.setStyle(clickEl, "visibility", "hidden");

        dragEl.innerHTML = clickEl.innerHTML;

        Dom.setStyle(dragEl, "color", Dom.getStyle(clickEl, "color"));
        Dom.setStyle(dragEl, "backgroundColor", Dom.getStyle(clickEl, "backgroundColor"));
        Dom.setStyle(dragEl, "border", "2px solid gray");
    },

    endDrag: function(e)
    {
        var srcEl = this.getEl();
        var proxy = this.getDragEl();

        // Show the proxy element and animate it to the src element's location
        Dom.setStyle(proxy, "visibility", "");
        var a = new YAHOO.util.Motion(
            proxy, {
                points: {
                    to: Dom.getXY(srcEl)
                }
            },
            0.2,
            YAHOO.util.Easing.easeOut
        )
        var proxyid = proxy.id;
        var thisid = this.id;

        // Hide the proxy and show the source element when finished with the animation
        a.onComplete.subscribe(function()
		{
			Dom.setStyle(proxyid, "visibility", "hidden");
			Dom.setStyle(thisid, "visibility", "");
		});
		a.animate();
	},

    onDragDrop: function(e, id)
    {
    	var sib = DDM.currentTarget;
		var type = sib.id.replace(/(dirs|files)_(\d)/, '$1');
   		var ix = sib.id.replace(/(dirs|files)_(\d)/, '$2');
   		console.dir(type);

   		document.getElementById(type+'_'+ix);

        // If there is one drop interaction, the li was dropped either on the list,
        // or it was dropped on the current location of the source element.
        if (DDM.interactionInfo.drop.length === 1)
        {
            // The position of the cursor at the time of the drop (YAHOO.util.Point)
            var pt = DDM.interactionInfo.point; 

            // The region occupied by the source element at the time of the drop
            var region = DDM.interactionInfo.sourceRegion; 

            // Check to see if we are over the source element's location.  We will
            // append to the bottom of the list once we are sure it was a drop in
            // the negative space (the area of the list without any list items)
            if (!region.intersect(pt))
            {
                var destEl = Dom.get(id);
                var destDD = DDM.getDDById(id);
                destEl.appendChild(this.getEl());
                destDD.isEmpty = false;
                DDM.refreshCache();
            }
        }
    },

    onDrag: function(e)
    {
        // Keep track of the direction of the drag for use during onDragOver
        var y = Event.getPageY(e);

        if (y < this.lastY) {
            this.goingUp = true;
        } else if (y > this.lastY) {
            this.goingUp = false;
        }

        this.lastY = y;
    },

    onDragOver: function(e, id)
    {
        var srcEl = this.getEl();
        var destEl = Dom.get(id);

        // We are only concerned with list items, we ignore the dragover
        // notifications for the list.
        if (destEl.nodeName.toLowerCase() == "li") {
            var orig_p = srcEl.parentNode;
            var p = destEl.parentNode;

            if (this.goingUp) {
                p.insertBefore(srcEl, destEl); // insert above
            } else {
                p.insertBefore(srcEl, destEl.nextSibling); // insert below
            }

            DDM.refreshCache();
        }
    }
});

Event.onDOMReady(YAHOO.example.DDApp.init, YAHOO.example.DDApp, true);

})();

</script>
EOF;

$t = new Template($context);
echo $t->Get('template_test.html');

?>