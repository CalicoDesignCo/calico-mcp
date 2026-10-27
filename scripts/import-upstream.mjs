import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const project=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const upstream=path.resolve(project,'../MCP Review 1.5.2/miniorange-secure-mcp-server');
const dest=path.join(project,'source/calico-mcp');
const common=[
 'apis/mcp/class-mcp-routes.php','apis/oauth/class-oauth-routes.php',
 'controllers/mcp/class-rest-controller.php','migration/class-migration.php',
 'repositories/class-store.php','repositories/class-nhi-store.php','repositories/class-audit-store.php','repositories/class-debug-store.php',
 'services/discovery/class-discovery.php','services/http/class-no-cache.php',
 'services/logging/class-audit-logger.php','services/logging/class-debug-logger.php',
 'services/mcp/class-mcp-server.php','services/oauth/class-oauth-server.php','services/oauth/class-tokens.php',
 'utils/class-utils.php','views/oauth/class-consent-view.php'
].map(f=>'includes/common/'+f);
const files=[...common];
for(const f of await fs.readdir(path.join(upstream,'libraries/abilities/src'))) {
 if(/^class-(ability|ability-pack|ability-registrar|config|naming)\.php$/.test(f)) files.push('libraries/abilities/src/'+f);
}
async function collect(dir) {
 for(const e of await fs.readdir(path.join(upstream,dir),{withFileTypes:true})) {
  if(e.isDirectory()) await collect(dir+'/'+e.name);
  else if(e.name.endsWith('.php') && e.name!=='index.php') files.push(dir+'/'+e.name);
 }
}
for(const dir of ['support','packs/core','packs/editing','packs/yoast']) await collect('libraries/abilities/src/'+dir);
files.push('libraries/abilities/src/packs/site/class-site-support.php');
function transform(s) {
 return s.replaceAll('miniorange-secure-mcp-server','calico-mcp').replaceAll('MoSMCP','CalicoMCP').replaceAll('MOSMCP','CALICOMCP').replaceAll('mosmcp','calicomcp').replaceAll('miniOrange Secure MCP Server','Calico MCP').replaceAll('Secure MCP Server','Calico MCP');
}
for(const file of files) {
 let text=transform(await fs.readFile(path.join(upstream,file),'utf8'));
 if(file.endsWith('class-store.php')) text=text.replace("array( 'gateway.miniorange.ai' )","array()");
 if(file.endsWith('apis/mcp/class-mcp-routes.php')) text=text.replace('// Public health / discovery probe.', `// GET OAuth probes receive the same challenge; authenticated SSE is unsupported.
        register_rest_route(CALICOMCP_REST_NAMESPACE,'/mcp',array(
            'methods'=>WP_REST_Server::READABLE,
            'callback'=>static function(){return new \\WP_REST_Response(array('error'=>'stream_not_supported'),405,array('Allow'=>'POST'));},
            'permission_callback'=>array(REST_Controller::class,'authenticate'),
        ));
        // Public health / discovery probe.`);
 if(file.endsWith('class-discovery.php')) {
  text=text.replace("'https://plugins.miniorange.com/'","Utils::issuer_url()");
  text=text.replace("add_rewrite_rule( '^\\.well-known/oauth-authorization-server(/.*)?/?$', 'index.php?calicomcp_wellknown=as', 'top' );", "add_rewrite_rule( '^\\.well-known/oauth-authorization-server(/.*)?/?$', 'index.php?calicomcp_wellknown=as', 'top' );\n\t\tadd_rewrite_rule( '^\\.well-known/openid-configuration/?$', 'index.php?calicomcp_wellknown=as', 'top' );");
  text=text.replace("} elseif ( preg_match( '#/\\.well-known/oauth-authorization-server(/.*)?$#', $path ) ) {", "} elseif ( preg_match( '#/\\.well-known/(?:oauth-authorization-server(/.*)?|openid-configuration/?)$#', $path ) ) {");
 }
 if(file.endsWith('class-consent-view.php')) text=text.replaceAll('stored encrypted','stored as keyed hashes').replaceAll('<svg viewBox=', '<svg width="18" height="18" viewBox=');
 if(file.endsWith('class-ysoa-helpers.php')) text=text.replace("WPSEO_Meta::set_value( $key, $value, $post_id );","if ( is_int( $value ) || is_float( $value ) ) { $value = (string) $value; }\n\t\tWPSEO_Meta::set_value( $key, $value, $post_id );");
 if(file.endsWith('class-naming.php')) text=text.replace("public static function register_ability( $name, $args ) {",`public static function register_ability( $name, $args ) {
        if (!\\CalicoMCP\\Personal\\Policy::supports($name)) { return false; }`);
 if(file.endsWith('controllers/mcp/class-rest-controller.php')) {
  text=text.replace("return self::unauthorized( 'invalid_token', array( 'bearer_prefix' => substr( $token, 0, 8 ) ) );","return self::unauthorized( 'invalid_token' );");
  text=text.replace(/^use .*Test_Token;\n/m,'').replace(/^use .*Rest_Bridge;\n/m,'');
  text=text.replace("MCP_Server::set_diagnostic( Test_Token::SCOPE === $row['scope'] );","MCP_Server::set_diagnostic( false );");
  text=text.replace(/^\s*Rest_Bridge::set_allowed_routes\([^\n]+\n/m,'\n');
  const start=text.indexOf('\t\tif ( MCP_Server::is_diagnostic()');
  const end=text.indexOf('\n\t\t$response = MCP_Server::dispatch',start);
  if(start!==-1) text=text.slice(0,start)+text.slice(end);
  text=text.replace("wp_set_current_user( (int) $row['user_id'] );",`wp_set_current_user( (int) $row['user_id'] );
        if (!\\CalicoMCP\\Personal\\Policy::is_owner((int)$row['user_id']) || !\\CalicoMCP\\Personal\\Policy::enabled()) {
            return self::unauthorized('access_disabled');
        }`);
 }
 if(file.endsWith('services/oauth/class-oauth-server.php')) {
  text=text.replace("$body = $request->get_json_params();",`if (!\\CalicoMCP\\Personal\\Policy::enabled()) { return new WP_REST_Response(array('error'=>'temporarily_unavailable'),503); }
        if (!\\CalicoMCP\\Personal\\Policy::registration_allowed()) { return new WP_REST_Response(array('error'=>'rate_limited'),429); }
        $body = $request->get_json_params();`);
  text=text.replace("if ( 'POST' === strtoupper(",`if (!\\CalicoMCP\\Personal\\Policy::is_owner(get_current_user_id()) || !\\CalicoMCP\\Personal\\Policy::enabled()) { wp_die('This connection is available only to the configured Calico MCP account.', '', array('response'=>403)); }
        if ( 'POST' === strtoupper(`);
  text=text.replace("$grant_type = (string) $request->get_param( 'grant_type' );",`if (!\\CalicoMCP\\Personal\\Policy::enabled()) { return self::token_error('access_denied','Connection disabled.',403); }
        $grant_type = (string) $request->get_param( 'grant_type' );`);
  text=text.replace("$refresh      = Tokens::generate();",`if (!\\CalicoMCP\\Personal\\Policy::is_owner((int)$user_id)) { return self::token_error('access_denied','Account is not authorized.',403); }
        $refresh      = Tokens::generate();`);
 }
 if(file.endsWith('services/mcp/class-mcp-server.php')) {
  // Remove the upstream gateway migration messages, not authentication checks.
  const start=text.indexOf('\t\tif ( self::$is_direct ) {',text.indexOf('private static function initialize_result'));
  const end=text.indexOf('\n\t\tif ( Debug_Logger::is_capturing()',start);
  text=text.slice(0,start)+`\t\t$result['instructions']='Calico MCP connects directly to this WordPress site. New content is draft-only. Before editing an existing item, read it and pass its revision as expected_revision. Editing published content changes the live site. Use the supplied schemas.';\n`+text.slice(end);
  const noticeStart=text.indexOf('\tprivate static function maybe_append_deprecation_notice');
  const noticeEnd=text.indexOf('\n\tprivate static function connect_page_url',noticeStart);
  // Replace only the function body using brace counting, preserving the following docblock.
  let brace=text.indexOf('{',noticeStart),depth=1,i=brace+1;
  while(depth && i<text.length) {if(text[i]==='{')depth++; if(text[i]==='}')depth--; i++;}
  text=text.slice(0,brace+1)+'\n        return $tool_result;\n\t}'+text.slice(i);
  text=text.replace("$abilities = apply_filters( 'calicomcp_exposed_abilities', $abilities );",`$abilities = array_filter($abilities, function($ability) { return \\CalicoMCP\\Personal\\Policy::allowed($ability->get_name()); });`);
  text=text.replace("$result   = $ability->execute( $input );",`$result = \\CalicoMCP\\Personal\\Guard::execute($ability, $input);`);
  text=text.replace("'inputSchema' => self::wrap_input_schema( $ability->get_input_schema() ),","'inputSchema' => self::wrap_input_schema( \\CalicoMCP\\Personal\\Guard::input_schema($ability) ),");
 }
 const output=path.join(dest,file);await fs.mkdir(path.dirname(output),{recursive:true});
 await fs.writeFile(output,text);
}
await fs.writeFile(path.join(project,'UPSTREAM-FILES.json'),JSON.stringify({upstream:'miniOrange Secure MCP Server 1.5.2',license:'MIT/Expat',files},null,2));
console.log('Imported '+files.length+' selected upstream files.');
