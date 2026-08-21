# Tmpl2

Lightweight template engine for PHP.

## Requirements

- PHP 7.4 or later

## Installation

### Composer
You can install Tmpl2 via Composer (once registered on Packagist):

- composer require ytahara-byte/tmpl2

### Manual installation
If you are not using Composer, download the source files from the `src/` directory and include them directly in your project:

    require_once 'src/Tmpl2.php';
    use Tmpl2\Tmpl2;
    $tmpl = new Tmpl2('template.html');

## Basic Usage

PHP:

    $tmpl = new Tmpl2('template.html');

    $tmpl->assign('TITLE', 'Hello Tmpl2');
    $echobuffer = $tmpl->render();

Template:

    <h1>%TITLE%</h1>

## Variables

    %NAME%

PHP:

    $tmpl->assign('NAME', 'Tmpl2');

## Loops

Template:
    <pre>
    <!-- tmpl:loop %ITEM% -->
    <li>%NAME%</li>
    <!-- tmpl:endloop %ITEM% -->
    </pre>

PHP:

    $tmpl->loopset('ITEM');
    foreach ($array as $value) {
        $tmpl->assign('NAME', $name);
        loopnext('ITEM');
    }
    loopend('ITEM')

## Conditional Blocks

### ifdef / ifndef
    <pre>
    <!-- tmpl:ifdef %USER% -->
    ...
    <!-- tmpl:endif -->
    </pre>

### else
    <pre>
    <!-- tmpl:ifdef %USER% -->
    ...
    <!-- tmpl:else -->
    ...
    <!-- tmpl:endif -->
    </pre>

## Loop Conditional Blocks

### ifldef / iflndef
    <pre>
    <!-- tmpl:ifldef %VALUE% -->
    ...
    <!-- tmpl:endifl -->
    </pre>
### else
    <pre>
    <!-- tmpl:ifldef %VALUE% -->
     ...
    <!-- tmpl:else -->
    ...
    <!-- tmpl:endifl -->
    </pre>
## Character Encoding / Escaping

### htmlspecialchars

#### default:
    flag (\ENT_QUOTES | ENT_SUBSTITUTE)
    encoding UTF-8

#### change function:
    $tmpl->setquotes(int flag);
    $tmpl->setencoding('SJIS-win');

## Public API

### __construct(string $filename = "")
    Creates a Tmpl2 instance.
### PHP souce
    $tmpl = new Tmpl2('template.html');
#### or
    $tmpl = new Tmpl2();
    $tmpl->loadTemplate('template.html');
#### or
    $tmpl = new Tmpl2();
    $tmpl->MemoryTmpl('<h1>%TITLE%</h1>');

### assign(string $name, $value)
 Assigns a value to a template variable.

### PHP souce:
 $tmpl->assign('TITLE', 'Hello World');

### Template:
    <pre>
    <h1>%TITLE%</h1>
    </pre>
### assign_def(string $name)
 Defines a variable for ifdef / ifndef.

### PHP souce:
 $tmpl->assign_def('LOGIN');

### Template:
 <pre>
 <!-- tmpl:ifdef %LOGIN% -->
 <p>Logged in</p>
 <!-- tmpl:endif -->
 </pre>


### loopset(string $name)
 Starts assigning values to a loop.
### loopnext(string $name)
 Moves to the next row of the current loop.
### loopend(string $name)
 Finishes the loop.

### PHP souce:
 $array = ['ABC','DEF','GHI'];
 $num = 0;
 $tmpl->loopset('DATA');
 foreach ($array as $value) {
     $tmpl->assign('CODE', $value);
     if ($num % 2 === 0) {
         $tmpl->assign_local_def('EVEN');    
     }
     loopnext('DATA');
 }
 loopend('DATA');

### Template:

#### type 1:
 <pre>
 <!-- tmpl:loop %DATA% -->
 <p>%CODE%</p>
 <!-- tmpl:endloop %DATA% -->
 </pre>

#### type 2:
 <pre>
 <!-- tmpl:loop %DATA% -->
 <!-- tmpl:ifldef %EVEN% -->
 <p>%CODE%</p>
 <!-- tmpl:else -->
 <p class="black">%CODE%</p>
 <!-- tmpl:endifl -->
 <!-- tmpl:endloop %DATA% -->
 </pre>
### render()
 Processes and outputs the template.

### PHP souce:
 $tmpl->render();

## Examples

See the `examples/` directory.

## Compatibility

Tmpl2 2.x supports PHP 7.4 and later.

## History

Tmpl2 originated in the PHP 4 era and has been maintained
and modernized while preserving its original lightweight
template syntax.

See `HISTORY.md` for details.

## License

See `LICENSE` for details.
