# Tmpl2

Lightweight template engine for PHP.

Tmpl2 can be used not only for HTML templates, but also for
plain text generation such as email bodies, CSV data, receipts,
XML, configuration files, and other text-based formats.

Template variables and control directives are independent of
the output format.

## Requirements

- PHP 7.4 or later

## Installation

### Composer

### Manual installation

## Supported Output Types

Tmpl2 is a text-based template engine and is not limited to HTML.

Typical uses include:

- HTML pages
- Email bodies
- Plain text
- CSV data
- Receipt / POS text data
- XML
- Configuration files
- Other text-based formats

HTML escaping is enabled by default.

For non-HTML output, escaping can be disabled:

    $tmpl->setquotes(0);
    
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

    <!-- tmpl:loop %ITEM% -->
    <li>%NAME%</li>
    <!-- tmpl:endloop %ITEM% -->

PHP:

    $tmpl->loopset('ITEM');
    foreach ($array as $value) {
        $tmpl->assign('NAME', $name);
        $tmpl->loopnext('ITEM');
    }
    $tmpl->loopend('ITEM')

## Conditional Blocks

### ifdef / ifndef

    <!-- tmpl:ifdef %USER% -->
    ...
    <!-- tmpl:endif -->


### else

    <!-- tmpl:ifdef %USER% -->
    ...
    <!-- tmpl:else -->
    ...
    <!-- tmpl:endif -->

## Loop Conditional Blocks

### ifldef / iflndef

    <!-- tmpl:ifldef %VALUE% -->
    ...
    <!-- tmpl:endifl -->

### else

    <!-- tmpl:ifldef %VALUE% -->
     ...
    <!-- tmpl:else -->
    ...
    <!-- tmpl:endifl -->
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
#### 
    $tmpl = new Tmpl2();
    $tmpl->loadTemplate('template.html');
#### 
    $tmpl = new Tmpl2();
    $tmpl->MemoryTmpl('<h1>%TITLE%</h1>');

### assign(string $name, $value)
 Assigns a value to a template variable.

### PHP souce:
 $tmpl->assign('TITLE', 'Hello World');

### Template:
    
    <h1>%TITLE%</h1>
### assign_def(string $name)
 Defines a variable for ifdef / ifndef.

### PHP souce:
 $tmpl->assign_def('LOGIN');

### Template:
  
    <!-- tmpl:ifdef %LOGIN% --> <p>Logged in</p>
    <!-- tmpl:endif -->

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
        $tmpl->loopnext('DATA');
    }
    $tmpl->loopend('DATA');

### Template:

#### type 1:

    <!-- tmpl:loop %DATA% -->
    <p>%CODE%</p>
    <!-- tmpl:endloop %DATA% -->

#### type 2:

    <!-- tmpl:loop %DATA% -->   
    <!-- tmpl:ifldef %EVEN% -->
        <p>%CODE%</p>
    <!-- tmpl:else -->
        <p class="black">%CODE%</p>
    <!-- tmpl:endifl -->
    <!-- tmpl:endloop %DATA% -->

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
