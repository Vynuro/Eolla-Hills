"""Copy proven Taabeer routines, adapting only their names and package target."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
original=(root/'reference/taabeer/taabeer-deployment-manager/inc/class-taabeer-deployment-manager.php').read_text(encoding='utf-8')
methods=[]
for name in ['encrypt_token','decrypt_token','record_theme_baseline','theme_file_manifest','find_local_theme_conflicts','zip_directory']:
    source=re.search(r'\t(?:private|public) function '+name+r'\b[\s\S]*?\n\t}',original).group(0)
    source=source.replace('taabeer-deployment','eola-hills-updates').replace('taabeer_','eola_hills_').replace('TAABEER','Eola Hills')
    source=source.replace("self::THEME_SLUG . '/' . substr( $path, strlen( $source ) + 1 )", "basename( $source ) . '/' . substr( $path, strlen( $source ) + 1 )")
    # Retain original conflict reconciliation; reject symlinks before reading packages.
    source=source.replace("\t\t\tif ( ! $file->isFile() ) {", "\t\t\tif ( $file->isLink() ) { return new WP_Error( 'symlink', 'Theme directory contains a symlink.' ); }\n\t\t\tif ( ! $file->isFile() ) {")
    methods.append(source)
target=root/'wordpress/wp-content/plugins/eola-hills-updates/inc/trait-taabeer-deployment.php'
target.write_text("<?php\n/** Directly reused from TAABEER Deployment Manager 1.0.1, GPL-2.0-or-later.\n * Theme deployment retained for the Eola Hills theme. */\ndefined( 'ABSPATH' ) || exit;\ntrait Eola_Hills_Taabeer_Deployment {\n"+'\n\n'.join(methods)+'\n}\n',encoding='utf-8')

foundation=Path('D:/Taabeer/wordpress/wp-content/themes/taabeer/inc/site-foundation.php').read_text(encoding='utf-8')
functions=[]
for name in ['taabeer_install_foundation_plugins','taabeer_ensure_elementor_kit']:
    source=re.search(r'function '+name+r'\b[\s\S]*?\n}',foundation).group(0).replace('taabeer_','eola_hills_')
    functions.append(source)
target=root/'wordpress/wp-content/plugins/eola-hills-updates/inc/taabeer-foundation.php'
target.write_text("<?php\n/** Copied from Taabeer site-foundation.php, GPL-2.0-or-later.\n * The dependency list is narrowed to Elementor for this property page. */\ndefined( 'ABSPATH' ) || exit;\nfunction eola_hills_required_plugins() { return array( 'elementor' => 'elementor/elementor.php' ); }\n"+'\n\n'.join(functions)+'\n',encoding='utf-8')
print('Copied six Taabeer updater methods and two foundation installer/kit routines.')
