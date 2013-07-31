<?xml version="1.0" encoding="utf-8" ?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:ttm="http://timberproject.org/tectona"
    exclude-result-prefixes="ttm" 
  >
    <xsl:output indent="yes" omit-xml-declaration="yes"
      method="text" />
     
     <xsl:template match="/">
       <xsl:apply-templates select="//ttm:body" />
     </xsl:template>
    
    <xsl:template match="ttm:body">
        <xsl:apply-templates />
    </xsl:template>
    
    <xsl:template match="ttm:*">         
        <xsl:variable name="myname" select="name()" />
        
        <xsl:if test="ancestor::ttm:template/ttm:subs/child::ttm:*[name() = $myname]" >
            <xsl:apply-templates select="ancestor::ttm:template/ttm:subs/child::ttm:*[name() = $myname]" mode="echo"/>
        </xsl:if>
    </xsl:template>
    
    <xsl:template match="ttm:*" mode="echo">
        <xsl:value-of select="text()" />
    </xsl:template>
</xsl:stylesheet>


