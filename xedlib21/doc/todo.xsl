<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
<html>
<head>
	<title> Todo List </title>
	<link href="../screen.css" type="text/css" rel="stylesheet" />
</head>
<body>

<p><b>
	<a href="index.xml">Index</a> :: Todo List
</b></p>

<xsl:for-each select="/todos/todo">
<div class="blue">
	<div class="head">
		<xsl:value-of select="@file" />:<xsl:value-of select="@line" />
	</div>

	<div class="body">
		<div class="item">
			<p><xsl:value-of select="text()" /></p>
		</div>
	</div>
</div><br/>
</xsl:for-each>

</body>
</html>
</xsl:template>
</xsl:stylesheet>