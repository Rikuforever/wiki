<?php

// Take credit for your work. Used on Special:Version
$wgExtensionCredits['parserhook'][] = array(
   'path' => __FILE__,
   'name' => 'Example Extension',

   // A description of the extension, which will appear on Special:Version.
   'description' => 'A simple example parser function extension',
   // Alternatively, you can specify a message key for the description.
   'descriptionmsg' => 'exampleextension-desc',

   'version' => '1.0.0', 
   'author' => 'Me',
   'url' => 'https://www.mediawiki.org/wiki/Extension:Example',
);

// Specify the function that will initialize the parser function.
$wgHooks['ParserFirstCallInit'][] = 'ExampleExtension::onParserSetup';

// Allow translation of the parser function name
$wgExtensionMessagesFiles['ExampleExtension'] = __DIR__ . '/ExampleExtension.i18n.php';


class ExampleExtension {
   // Register any render callbacks with the parser
   function onParserSetup( &$parser ) {

      // Create a function hook associating the "foo" magic word with renderFoo()
      $parser->setFunctionHook( 'simin', 'ExampleExtension::renderSimin' );
   }

   // Render the output of {{foo}}.
   function renderSimin( $parser, $param1 = '', $param2 = '', $param3 = '' ) {

      // The input parameters are wikitext with templates expanded.
      // The output should be wikitext too.
      $output = "param1 is $param1 and param2 is $param2 and param3 is $param3";

      return $output;
   }
}