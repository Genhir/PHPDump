<?php
/* Version 0.1 - Sebastian Zartner
 *
 * Distributed under the MIT license. See the LICENSE file.
 */

require_once 'docParser.php';

ob_start();

class DBG {
  private $var;
  private $docParser;

  function __construct($var) {
    $this->var = $var;
    $this->docParser = new DocParser();
  }

  private function isIndexedArray($arr) {
    if (!is_array($arr))
      return false;
    $keys = array_keys($arr);
    foreach ($keys as $key) {
      if (!is_int($key))
        return false;
    }

    return true;
  }

  private function dumpBoolean($var) {
    return '<div class="boolean">' . ($var ? 'TRUE' : 'FALSE') . '</div>';
  }

  private function dumpNull($var) {
    return '<div class="null">NULL</div>';
  }

  private function dumpArray($var) {
    $isIndexedArray = $this->isIndexedArray($var);
    $class = $isIndexedArray ? 'indexedArray' : 'associativeArray';
    $title = $isIndexedArray ? 'Indexed Array' : 'Associative Array';
    if (count($var) === 0)
      $title .= ' [empty]';

    $out = <<<OUTPUT
      <table class="debug {$class}">
        <thead>
          <tr>
            <th colspan="2">{$title}</th>
          </tr>
        </thead>
        <tbody>
OUTPUT;

    foreach ($var as $key => $value) {
      $out .= <<< OUTPUT
          <tr>
            <td class="label">{$key}</td>
            <td>{$this->dumpVariable($value)}</td>
          </tr>
OUTPUT;
    }

    $out .= <<< OUTPUT
        </tbody>
      </table>
OUTPUT;

    return $out;
  }

  private function dumpFunction($var) {
    if ($var instanceof ReflectionFunctionAbstract) {
      $out = <<< OUTPUT
                  <tr>
                    <td class="label">{$var->name}</td>
                    <td>
                      <div class="methodInfo">
                        <span class="label">Arguments:</span>
OUTPUT;

      $params = $var->getParameters();
      if (count($params) > 0) {
        $out .= <<< OUTPUT
                          <table class="args">
                            <thead>
                              <tr>
                                <th>Name</th>
                                <th>Required</th>
                                <th>Default</th>
                              </tr>
                            </thead>
                            <tbody>
OUTPUT;

        foreach ($params as $param) {
          try {
            $defaultValue = $param->getDefaultValue();
          } catch (Exception $e) {
            $defaultValue = 'not available';
          }
          $out .= <<< OUTPUT
                              <tr>
                                <td>{$param->name}</td>
                                <td>{$this->dumpVariable($param->isOptional())}</td>
                                <td>{$this->dumpVariable($defaultValue)}</td>
                              </tr>
OUTPUT;
        }

      $out .= <<< OUTPUT
                              </tbody>
                          </table>
OUTPUT;
      } else
          $out .= '<span>none</span>';

      $out .= <<< OUTPUT
                          <div>
                            <span class="label">Static:</span>
                            <span>{$this->dumpVariable($var->isStatic())}</span>
                          </div>
OUTPUT;

      $description = $var->getDocComment();
      if ($description !== false) {
      	$this->docParser->parse($description);

        $out .= <<< OUTPUT
      	                  <div>
	                          <span class="label">Description:</span>
	                          <span>{$this->dumpVariable($this->docParser->getShortDesc())}</span>
	                        </div>
OUTPUT;

        $returnTag = $this->docParser->getTag('return');
        if ($returnTag !== [])
        $out .= <<< OUTPUT
      	                  <div>
	                          <span class="label">Return value:</span>
	                          <span>{$this->dumpVariable($returnTag['type'])}</span>
	                        </div>
OUTPUT;
      }

      $out .= <<< OUTPUT
                        </div>
	                    </td>
	                  </tr>
OUTPUT;

      return $out;
    }
  }

  private function dumpObject($var) {
    $reflection = new ReflectionObject($var);

    switch ($reflection->name) {
    	case 'PDOStatement':
    		$colCount = $var->columnCount();
    		$colsMetaData = [];
    		for ($i = 0; $i < $colCount; $i++)
    		  array_push($colsMetaData, $var->getColumnMeta($i));
    		$colSpan = $colCount + 1;
    	  $out = <<<OUTPUT
        <table class="debug query">
            <thead>
                <tr>
                    <th colspan="2">Query</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Result set</td>
                    <td>
							    	  <table class="debug queryResult">
							            <thead>
							                <tr>
							                    <th colspan="{$colSpan}">Query Result</th>
							                </tr>
							            </thead>
							            <tbody>
							                <tr>
							                    <td></td>
OUTPUT;

    	  foreach ($colsMetaData as $colMetaData)
          $out .= '<td>' . $colMetaData['name'] . '</td>';
        $out .= '</tr>';

        $rows = $var->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $index => $row) {
          $out .= <<< OUTPUT
                <tr>
                    <td>{$index}</td>
OUTPUT;
          foreach ($row as $cell)
          	$out .= '<td>' . $cell . '</td>';
          $out .= '</tr>';
        }

        $out .= <<< OUTPUT
			              <tr>
				          </tbody>
				        </table>
              </td>
        		</tr>
        		<tr>
        		  <td>SQL</td>
        		  <td>{$var->queryString}</td>
            </tr>
          </tbody>
        </table>
OUTPUT;
    	  break;

    	case 'SimpleXMLElement':
    		$out = <<<OUTPUT
        <table class="debug xml">
          <thead>
            <tr>
              <th colspan="2">XML</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="label">{$var->getName()}</td>
              <td>
                <table class="xml">
                  <tbody>
                    <tr>
                      <td class="label">XmlText</td>
	                    <td>{$this->dumpVariable($var->__toString())}</td>
	                  </tr>
OUTPUT;

    		$arr = (array)$var;
    		if (isset($arr['@attributes'])) {
    		  $attributes = $arr['@attributes'];
    			$out .= <<<OUTPUT
                    <tr>
                      <td class="label">XmlAttributes</td>
	                    <td>{$this->dumpVariable($attributes)}</td>
	                  </tr>
OUTPUT;
    		}

    		if ($var->count() !== 0) {
	    		$out .= <<<OUTPUT
                    <tr>
                      <td class="label">XmlChildren</td>
                      <td>
OUTPUT;

	    		foreach ($var->children() as $child)
	    			$out .= $this->dumpVariable($child);

	    		$out .= <<<OUTPUT
	    		            </td>
                    </tr>
OUTPUT;
    		}

    		$out .= <<<OUTPUT
                  </tbody>
                </table>
    		      </td>
            </tr>
          </tbody>
        </table>
OUTPUT;
    		break;

      default:
        $out = <<<OUTPUT
      <table class="debug object">
        <thead>
          <tr>
            <th colspan="2">Object {$reflection->name}</th>
          </tr>
        </thead>
        <tbody>
OUTPUT;

		    $props = $reflection->getProperties();
		    if (count($props) > 0) {
		      $out .= <<<OUTPUT
        <tr>
          <td class="label">Properties</td>
          <td>
            <table class="props">
              <tbody>
OUTPUT;

		      foreach ($props as $prop) {
		        $prop->setAccessible(true);

		        $out .= <<< OUTPUT
                  <tr class="prop">
                    <td class="label">{$prop->name}</td>
                    <td>{$this->dumpVariable($prop->getValue($var))}</td>
                  </tr>
OUTPUT;
		      }

		      $out .= <<<OUTPUT
              </tbody>
            </table>
          </td>
        </tr>
OUTPUT;
		    }

		    $methods = $reflection->getMethods();
		    if (count($methods) > 0) {
		      $out .= <<<OUTPUT
        <tr>
          <td class="label">Methods</td>
          <td>
            <table class="methods">
              <tbody>
OUTPUT;

		      foreach ($methods as $method) {
		      	if (!$method->isInternal()) {
		          $method->setAccessible(true);
		          $out .= $this->dumpFunction($method);
		      	}
		      }

		      $out .= <<<OUTPUT
              </tbody>
            </table>
          </td>
        </tr>
OUTPUT;
		    }

		    $out .= <<< OUTPUT
        </tbody>
      </table>
OUTPUT;
    }

    return $out;
  }

  private function dumpResource($var) {
    return get_resource_type($var);
  }

  private function dumpDefault($var) {
    return $var === '' ? '<div class="emptyString">[empty string]</div>' : '<div>' . (string) $var . '</div>';
  }

  private function dumpVariable($var) {
    switch (gettype($var)) {
      case 'boolean':
        return $this->dumpBoolean($var);

      case 'NULL':
        return $this->dumpNull($var);

      case 'array':
        return $this->dumpArray($var);

      case 'object':
        return $this->dumpObject($var);

      case 'resource':
        return $this->dumpResource($var);

      default:
        return $this->dumpDefault($var);
    }
  }

  public function __toString() {
    return $this->dumpVariable($this->var);
  }
}

function dump($var) {
  echo new DBG($var);
}

function endOutput() {
  define('CLOSING_HEAD_TAG', '</head>');
  define('CLOSING_BODY_TAG', '</body>');

  $debugStyleSheet = <<<STYLESHEET
        <style type="text/css">
        .debug,
        .debug table {
            font-family: Verdana, Arial, Helvetica, sans-serif;
            font-size: xx-small;
            border-collapse: collapse;
        }

        .debug TD {
            padding: 3px;
        }

        .debug TH {
            padding: 5px;
            cursor: pointer;
            text-align: left;
        }

        .debug > THEAD > TR > TH,
        .debug > TBODY > TR > TD {
            border: 2px solid #000;
            vertical-align: top;
        }

        .debug .label {
            cursor: pointer;
        }

        .boolean,
        .emptyString,
        .null {
            font-style: italic;
        }

        .indexedArray > THEAD > TR > TH,
        .indexedArray > TBODY > TR > TD {
            border-color: #060;
        }

        .indexedArray .label {
            background-color: #cfc;
        }

        .indexedArray > THEAD > TR > TH {
            background-color: #090;
            color: #fff;
        }

        .associativeArray > THEAD > TR > TH,
        .associativeArray > TBODY > TR > TD {
            border-color: #00c;
        }

        .associativeArray .label {
            background-color: #cdf;
        }

        .associativeArray > THEAD > TR > TH {
            background-color: #44c;
            color: #fff;
        }

        .object > THEAD > TR > TH,
        .object > TBODY > TR > TD {
            border-color: #e00;
        }

        .props > TBODY > TR > TD,
        .methods > TBODY > TR > TD {
            border: 2px solid #e00;
            vertical-align: top;
        }

        .object .prop > .label {
            background-color: #fcc;
        }

        .object > TBODY > TR > .label,
        .methods > TBODY > TR > .label {
            background-color: #f9a;
        }

        .object > THEAD > TR > TH {
            background-color: #f44;
            color: #fff;
        }

        .methodInfo {
            display: flex;
            flex-direction: column;
        }

        .methodInfo > DIV {
            display: flex;
        }

        .methodInfo SPAN.label {
            display: inline-block;
            width: 80px;
            background-color: #fff;
            font-style: italic;
        }

        .args > THEAD > TR > TH,
        .args > TBODY > TR > TD {
            border: 2px solid #ddd;
        }

        .args > THEAD > TR > TH {
            background-color: #eee;
        }

        .args > THEAD > TR > TH {
            background-color: #eee;
        }

  		  .query > THEAD > TR > TH,
        .query > TBODY > TR > TD,
  		  .queryResult > THEAD > TR > TH,
        .queryResult > TBODY > TR > TD {
            border-color: #848;
        }

        .query > TBODY > TR > TD:first-child,
  		  .queryResult > TBODY > TR:first-child > TD,
        .queryResult > TBODY > TR > TD:first-child {
            background-color: #fdf;
        }

        .query > THEAD > TR > TH,
        .queryResult > THEAD > TR > TH {
            background-color: #a6a;
            color: #fff;
        }

        .xml > THEAD > TR > TH,
        .xml > TBODY > TR > TD {
            border: 2px solid #888;
        }

        .xml > TBODY > TR > .label {
            background-color: #ddd;
        }

        .xml > THEAD > TR > TH {
            background-color: #aaa;
            color: #fff;
        }
        </style>
STYLESHEET;

  $out = ob_get_contents();
  ob_end_clean();

  $closingTagPos = stripos($out, CLOSING_HEAD_TAG);
  if ($closingTagPos !== false) {
    $out = substr($out, 0, $closingTagPos) . $debugStyleSheet .
        CLOSING_HEAD_TAG . substr($out, $closingTagPos + strlen(CLOSING_HEAD_TAG));
  } else {
    $closingTagPos = stripos($out, CLOSING_BODY_TAG);
    if ($closingTagPos !== false) {
      $out = substr($out, 0, $closingTagPos) . $debugStyleSheet .
          CLOSING_BODY_TAG . substr($out, $closingTagPos + strlen(CLOSING_BODY_TAG));
    } else
      $out += $debugStyleSheet;
  }

  echo $out;
}
?>