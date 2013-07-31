<?php
namespace Tectona;
use \Timber\XML\DOMDocument;
use \DOMXpath;
use \Exception;
use \XSLTProcessor;
use \Timber\Utils\Validator;

/** @file Mailer
 *
 * @author  Tim Zurak <zurakt@gmail.com>
 * @author  hacked by Mase <andrew.mason@digicom.net.au>
 * @license http://www.gnu.org/licenses/gpl.txt GPLv3
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPL v3
 *
 *#############################################################################*
 *                                                                             *
 *                     This file is part of Tectona.                           *
 *                                                                             *
 *    Tectona is free software: you can redistribute it and/or modify          *
 *    it under the terms of the GNU General Public License and                 *
 *    the GNU Lesser General Public License  as published by                   *
 *    the Free Software Foundation, either version 3 of the License, or        *
 *                     (at your option) any later version.                     *
 *                                                                             *
 *       Tectona is distributed in the hope that it will be useful,            *
 *        but WITHOUT ANY WARRANTY; without even the implied warranty of       *
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        *
 *                 GNU General Public License for more details.                *
 *                                                                             *
 *      You should have received a copy of the GNU General Public License      *
 *    along with Tectona.  If not, see <http://www.gnu.org/licenses/>.         *
 *                                                                             *
 *#############################################################################*
 **/


/**
 * Simple Mail Merge / Send class for the Timber framework.
 *
 * Basic Schema for the mailer described in the documentation dir
 *
 *
 */
final class Mailer
{

    const NS = 'http://timberproject.org/tectona';
    const TO   = 1;
    const FROM = 2;

    public $dom;               ///< DOMDocument representation of the source document
    public $xsltProc;          ///< XSLT Processor
    public $template;          ///< XSLT to use to transform
    public $subject;           ///< Override the Subject address
    public $envelopeSender;    ///< Override the sender address in the email envelope
    public $subsElement;       ///< Element to append the substitutes to
    public $fromName;          ///< Name of the sender
    public $fromEmail;         ///< Address of the sender
    public $toEmail;           ///< Address of the recipient
    public $toName;            ///< Name of the recipient
    public $body;              ///< Array of body contents
    public $debug;             ///< Turn on/off debugging
    public $reallySend = true; ///< Actually send the mail

    /**
     * __construct: Instanciate Mailer
     *
     *
     */
    public function __construct( $xslFileName, $debug=null )
    {
        $this->dom = new DOMDocument();

        stream_wrapper_register('xsl', '\Timber\Streams\XSLStreamLoader');

        // need to turn this off or you get unwanted whitespace in the output
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput=false;

        $this->body = array(
            'text' => null,
            'html' => null
        );

        $this->template = $xslFileName;
        if(!is_file($xslFileName))
        {
            throw new Exception('File Not Found: '.$xslFileName,0);
        }
        $this->xsltProc = new XSLTProcessor();
        $dom = new DOMDocument('1.0','UTF-8');
        $dom->load($this->template);
        $this->xsltProc->importStylesheet( $dom );

    }

    public function importMetaValues()
    {

        foreach( $this->dom->getNodes('.//ttm:meta/*') as $child )
        {
            $this->{$child->nodeName} = \trim($child->nodeValue);
        }
    }

    /**
     *
     * @param string $file Path to the xml file
     *
     */
    public function setSourceFile($file)
    {
        if(is_file($file))
        {
            if($this->dom->load($file, LIBXML_COMPACT))
            {
                $this->dom->xinclude(LIBXML_COMPACT);
                $this->importMetaValues();
                return $this->setSubsElement();
            }
        }
        throw new Exception('File Not Found: '.$file,0);
    }

    /**
     *
     * @param DOMDocument $dom Document to use as the source
     *
     */
    public function setSourceDOM( \DOMDocument $dom )
    {
        $this->dom = $dom;
        $this->importMetaValues();
        return $this->setSubsElement();
    }

    /**
     *
     *
     */
    public function appendXMLToSubs( $string )
    {
        if( $this->subsElement != false )
        {
            $frag = $this->dom->createDocumentFragment();
            $frag->appendXML($string);

            $this->subsElement = $this->subsElement->parentNode->replaceChild($frag,$this->subsElement);
            return $this->subsElement;
        }
    }

    /**
     *
     *
     */
    public function appendArrayToSubs( array &$substitutions)
    {

        if( $this->subsElement != false )
        {
            foreach( $substitutions as $name=>$val )
            {
                // AM JWS Modificiation. Have to filter manually since there
                // the Timber DOM handles this for us.
                $val = Validator::filterForXML($val);
                $this->subsElement->appendChild( $this->dom->createElementNS(self::NS,strval($name),trim($val)));
            }
            return true;
        }
    }

    /**
     *
     *
     */
    public function merge()
    {
         // Render the text / html
        foreach( $this->body as $key=>$val )
        {
            $this->xsltProc->setParameter('','type',$key);
            $this->body[$key] = $this->xsltProc->transformToXML($this->dom);
        }

        // replace entities in textual content as the original source is marked up in XML
        // so < will be &lt;
        $this->body['text'] = Validator::unfilter($this->body['text']);
    }


    public function send()
    {
        if($this->body['text'] == NULL )
        {
            $this->merge();
        }
        $subject   = $this->subject ; //subject
        $recipient = $this->formatAddress(self::TO);
        $headers   = 'From: '.$this->formatAddress(self::FROM);
        $boundary  = sha1(\time().'-'.\php_uname('n') );
        $headers   .= 'Content-ID: '.$boundary."\n";
        $headers   .= 'MIME-Version: 1.0'."\n";
        $headers   .= "Content-Type: multipart/alternative;boundary=\"$boundary\";\n\n";
        $body      = $this->formatBody($boundary);


        if( Validator::strNotEmpty($recipient) &&
            Validator::strNotEmpty($subject) &&
            Validator::strNotEmpty($body) &&
            Validator::strNotEmpty($this->envelopeSender))
         {
            // @todo strip out the brackets <>
            $this->log('Recipient: '.$recipient);
            $this->log('Subject : '.$this->subject);
            $this->log('Headers '.print_r($headers,true));
            $this->log('EnvelopeSender: '.$this->envelopeSender);

            // @todo run addresses through and email address validator
            if( $this->reallySend === true )
            {
                return mail($recipient, $this->subject, $body, $headers, '-f '.$this->envelopeSender);
            }
            return true;
        }
        throw new \Exception('Required Content Missing');
    }


    private function formatBody($boundary)
    {
        $message = '';
        if( isset($this->body['text']))
        {
            $message .= "\r\n\r\n--" . $boundary . "\r\n";
            $message .= "Content-type: text/plain;charset=utf-8\r\n\r\n";
            $message .= $this->body['text'];
        }
        if(isset($this->body['html']))
        {
            $message .= "\r\n\r\n--" . $boundary . "\r\n";
            $message .= "Content-type: text/html;charset=utf-8\r\n\r\n";
            $message .= $this->body['html'];
        }
        $message .= "\r\n\r\n--" . $boundary . '--';

        return $message;
    }

    private function formatAddress($type)
    {
        switch($type)
        {
            case self::TO:
                if(!empty($this->toName))
                {
                    return $this->toName . ' <' . $this->toEmail . ">\r\n";
                }
                return $this->toEmail."\r\n";
            case self::FROM:
                if(!empty($this->fromName))
                {
                    return $this->fromName . ' <' . $this->fromEmail . ">\r\n";
                }
                return $this->fromEmail."\r\n";;
                break;
        }

    }

    private function setSubsElement()
    {
        // Grab the subs element
        $proc = new DOMXpath($this->dom);
        $proc->registerNamespace('ttm', self::NS );
        if( $nodes = $proc->evaluate('//ttm:subs') )
        {
            $this->subsElement = $nodes->item(0);
            return;
        }
        throw new Exception('No "subs" element found in document: ',0);
    }

    public function log($msg,$level=E_USER_NOTICE)
    {
        trigger_error('[TECTONA] '.$msg,$level);
    }

}