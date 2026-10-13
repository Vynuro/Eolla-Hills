"""Generate the native Elementor Eola Hills editorial property landing page."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'wordpress/wp-content/plugins/eola-hills'
INK='#06265A'; PAPER='#FCFDFF'; SAND='#F3F7FD'; BLUE='#0044A8'; MUTED='#627795'; WHITE='#FFFFFF'; ICE='#DCE9FF'
counter=0
def ident():
 global counter
 counter+=1
 return hashlib.sha1(f'eola-v2-{counter}'.encode()).hexdigest()[:8]
def size(n,unit='px'):return {'unit':unit,'size':n,'sizes':[]}
def edges(t=0,r=None,b=None,l=None):return {'unit':'px','top':str(t),'right':str(t if r is None else r),'bottom':str(t if b is None else b),'left':str(t if l is None else l),'isLinked':False}
def gap(n):return {'unit':'px','size':n,'column':str(n),'row':str(n),'isLinked':True}
def media(key):return {'url':f'{{{{EOLA_ASSETS}}}}/photos/{key}.webp','id':0}
def widget(kind,name,**s):return {'id':ident(),'elType':'widget','widgetType':kind,'settings':{'_title':name,**s},'elements':[]}
def heading(copy,tag='h2',n=48,color=INK,serif=True,**s):
 return widget('heading',copy.replace('<br>',' '),**{'title':copy,'header_size':tag,'title_color':color,'typography_typography':'custom','typography_font_family':'Eola Display' if serif else 'Eola Sans','typography_font_size':size(n),'typography_font_size_tablet':size(min(n,48)),'typography_font_size_mobile':size(min(n,38)),'typography_font_weight':'600' if serif or tag in ('h1','h2','h3') else '500','typography_line_height':size(1.12,'em'),'typography_letter_spacing':size(-1 if n>40 else 0),**s})
def label(copy,color=BLUE,**s):return heading(copy,'p',11,color,False,**{'typography_letter_spacing':size(1.8),'typography_line_height':size(1.5,'em'),**s})
def text(copy,color=MUTED,n=15,**s):return widget('text-editor',copy[:40],**{'editor':f'<p>{copy}</p>','text_color':color,'typography_typography':'custom','typography_font_family':'Eola Sans','typography_font_size':size(n),'typography_line_height':size(1.5,'em'),**s})
def button(copy,url,light=False,outline=False,**s):
 color=WHITE if light else INK
 return widget('button',copy,**{'text':copy,'link':{'url':url},'align':'left','button_text_color':color if outline else (INK if light else WHITE),'background_color':'#FFFFFF00' if outline else color,'button_background_hover_color':ICE,'hover_color':INK,'border_border':'solid','border_width':edges(1),'border_color':color if outline else '#FFFFFF00','border_radius':edges(50),'text_padding':edges(17,26,17,26),'typography_typography':'custom','typography_font_family':'Eola Sans','typography_font_size':size(12),'typography_font_weight':'500',**s})
def container(name,children,**s):return {'id':ident(),'elType':'container','isInner':False,'settings':{'_title':name,'content_width':'full','flex_direction':'column','flex_gap':gap(16),'padding':edges(0),'flex_align_items':'stretch',**s},'elements':children}
def row(name,children,**s):return container(name,children,**{'flex_direction':'row','flex_direction_mobile':'column','flex_justify_content':'space-between',**s})
def col(name,children,width=48,**s):return container(name,children,**{'width':size(width,'%'),'width_mobile':size(100,'%'),**s})
def section(name,children,anchor=None,**s):return container(name,children,**{'html_tag':'section','content_width':'boxed','boxed_width':size(1200),'padding':edges(76,48,76,48),'padding_tablet':edges(56,28,56,28),'padding_mobile':edges(42,22,42,22),'css_classes':'eola-reveal-section',**({'_element_id':anchor} if anchor else {}),**s})
def photo(key,title,height=380,**s):return widget('image',title,**{'image':media(key),'image_size':'full','width':size(100,'%'),'height':size(height),'height_mobile':size(280),'object-fit':'cover','caption_source':'none','image_border_radius':edges(22),**s})
def background(key,overlay='#06265A88'):
 return {'background_background':'classic','background_image':media(key),'background_position':'center center','background_size':'cover','background_overlay_background':'classic','background_overlay_color':overlay,'background_overlay_opacity':size(1)}
def card(name,children,**s):return container(name,children,**{'background_background':'classic','background_color':SAND,'border_radius':edges(24),'padding':edges(28),'css_classes':'eola-lift-card',**s})
def center(items):
 for i in items:i['settings']['align']='center'
 return items

tour='mailto:linda.lindsay@exprealty.com?subject=Private%20tour%20-%209055%20SE%20Eola%20Hills%20Rd'
nav=row('Header - editable brand and navigation',[
 col('Wine Country Properties + eXp',[heading('Wine Country','p',28,typography_font_weight='500'),label('PROPERTIES / eXp',BLUE,typography_font_size=size(9))],33.33,flex_gap=gap(4)),
 row('Navigation',[button('The property','#property',outline=True,_css_classes='eola-nav-link'),button('Gallery','#gallery',outline=True,_css_classes='eola-nav-link'),button('The team','#team',outline=True,_css_classes='eola-nav-link')],width=size(43,'%'),width_mobile=size(100,'%'),flex_direction_mobile='row',flex_wrap='wrap',flex_justify_content='center',flex_justify_content_mobile='flex-start',flex_gap=gap(8)),
 col('Header tour action',[button('Schedule a Private Tour ↗',tour)],33.33,flex_align_items='flex-end',hide_mobile='hidden-mobile')
],flex_gap=gap(0),flex_gap_mobile=gap(18),flex_align_items='center',flex_align_items_mobile='flex-start',css_classes='eola-header',_element_id='top')
nav['elements'][1]['settings']['width']=size(33.33,'%')
for w in nav['elements'][1]['elements']:
 w['settings'].update(text_padding=edges(12,16,12,16),border_width=edges(0),typography_font_size=size(12))
header=section('Header',[nav],html_tag='header',padding=edges(24,48,24,48),padding_tablet=edges(22,28,22,28),padding_mobile=edges(22,28,12,28),css_classes='eola-site-header')
hero_copy=col('Hero introduction',[
 button('9055 SE EOLA HILLS RD','#details',True,True,text_padding=edges(12,18,12,18),typography_font_size=size(10),typography_letter_spacing=size(1.2),border_radius=edges(999),border_color='#FFFFFF80',background_color='#06265A66',selected_icon={'value':'fas fa-bullseye','library':'fa-solid'},icon_align='left',icon_indent=10,_css_classes='eola-address-pill'),
 heading('Unmistakably<br>special.','h1',78,WHITE,typography_font_size_tablet=size(60),typography_font_size_mobile=size(37),typography_line_height=size(1.08,'em')),
 text('A rare combination of home, land,<br>lifestyle & possibility.','#EEF4FF',17),
 row('Hero actions',[button('Schedule a private tour ↗',tour,True),button('View property highlights ↓','#property',True,True)],flex_justify_content='flex-start',flex_wrap='wrap',flex_gap=gap(12))
],62,css_classes='eola-hero-copy',flex_gap=gap(19))
offer=card('Property offering',[
 label('PROPERTY DETAILS',BLUE),heading('$1,499,000','p',34,serif=False,typography_font_size_mobile=size(30)),
 text('3 Beds · 3 Baths<br>Approx. 3,112 Sq Ft',INK,14),
 button('Property details ↗','#details',outline=True,text_padding=edges(12,18,12,18))
],width=size(30,'%'),width_mobile=size(100,'%'),background_color=PAPER,padding=edges(28),padding_mobile=edges(24),border_radius=edges(24),flex_gap=gap(18),css_classes='eola-hero-offer')
scenes=row('Hero photo selector',[button(copy,'#main-content',True,True,text_padding=edges(9,18,9,18),border_color='#FFFFFF55',_css_classes='eola-scene-button eola-scene-'+key) for copy,key in [('01 / Exterior','exterior'),('02 / Interiors','interior'),('03 / The land','land')]],flex_direction_mobile='row',flex_wrap='wrap',flex_justify_content='flex-start',flex_gap=gap(8),padding=edges(8,0,0,0))
hero=section('Hero - cinematic property introduction',[
 button('AMITY, OREGON  ·  4.89 ACRES','#land',True,text_padding=edges(13,20,13,20),typography_font_size=size(10),typography_letter_spacing=size(1)),
 container('Photograph breathing space',[],min_height=size(75),min_height_mobile=size(35)),
 row('Headline and property offering',[hero_copy,offer],flex_align_items='flex-end',flex_align_items_mobile='stretch',flex_gap=gap(30)),
 scenes
],anchor='main-content',**{k:v for k,v in background('hero').items() if k not in ['background_overlay_background','background_overlay_color']},background_overlay_background='gradient',background_overlay_color='#061D4905',background_overlay_color_b='#061D49F5',background_overlay_color_stop=size(0,'%'),background_overlay_color_b_stop=size(100,'%'),background_overlay_gradient_angle=size(180,'deg'),padding=edges(32,48,32,48),padding_tablet=edges(28),padding_mobile=edges(24),border_radius=edges(28),boxed_width=size(1260),min_height=size(720),min_height_mobile=size(0),flex_gap=gap(18),flex_justify_content='space-between',css_classes='eola-immersive-hero')

stats=[]
for title,value,icon in [('BEDROOMS','3','bed'),('BATHROOMS','3','bath'),('APPROX. SQ FT','3,112','ruler-combined')]:
 stats.append(col(title,[
 widget('icon',title+' icon',selected_icon={'value':'fas fa-'+icon,'library':'fa-solid'},view='stacked',shape='square',primary_color=ICE,secondary_color=BLUE,size=size(19),icon_padding=size(14),border_radius=edges(13),align='left'),
 heading(value,'p',48,serif=False,_css_classes='eola-stat-count',typography_font_weight='600'),label(title,INK,typography_letter_spacing=size(.5))
 ],23,background_background='classic',background_color=WHITE,padding=edges(38,30,34,30),padding_tablet=edges(28,18,28,18),padding_mobile=edges(28,24,28,24),width_mobile=size(100,'%'),flex_gap=gap(15),border_border='solid',border_width=edges(0,0,0,1),border_width_mobile=edges(1,0,0,0),border_color='#D5E2F7'))
summary=section('Property essentials',[
 row('Essentials introduction',[
 col('Introduction',[label('PROPERTY DETAILS'),heading('Property at a Glance',n=40,serif=False,typography_font_size_mobile=size(30))],72),
 button('Explore the property','#details',outline=True,text_padding=edges(14,20,14,20),selected_icon={'value':'fas fa-arrow-right','library':'fa-solid'},icon_align='right',icon_indent=15)
 ],flex_align_items='center',flex_align_items_mobile='flex-start'),
 container('Essentials connected panel',[
 row('Essential numbers',[
 col('Acreage feature',[
 label('OREGON WINE COUNTRY',ICE,typography_font_size=size(10)),heading('4.89','p',76,WHITE,False,typography_font_weight='600',typography_font_size_mobile=size(64)),heading('Acres','h3',21,WHITE,False),text('Space, privacy and versatility.',WHITE,12)
 ],31,width_mobile=size(100,'%'),padding=edges(38,34,34,34),padding_tablet=edges(28,24,28,24),background_background='gradient',background_color='#061D49',background_color_b='#1553A6',background_gradient_angle=size(35,'deg'),flex_gap=gap(18),css_classes='eola-acreage-feature'),
 *stats
 ],flex_gap=gap(0),css_classes='eola-facts-strip'),
 row('Property essentials footer',[
 col('Location',[label('AMITY, OREGON',INK),text('9055 SE Eola Hills Rd',n=12)],34),
 col('Property character',[label('HOME, LAND, LIFESTYLE & POSSIBILITY',INK),text('A rare combination of home, land, lifestyle & possibility.',n=12)],34),
 col('Listing representation',[label('WINE COUNTRY PROPERTIES',INK),text('Private Tours & Property Information',n=12)],32)
 ],padding=edges(24,30,24,30),background_background='classic',background_color=WHITE,flex_gap=gap(24),border_border='solid',border_width=edges(1,0,0,0),border_color='#D5E2F7')
 ],border_border='solid',border_width=edges(1),border_color='#D5E2F7',border_radius=edges(24),overflow='hidden',flex_gap=gap(0))
],padding=edges(58,48,45,48),padding_mobile=edges(38,22,30,22),background_background='classic',background_color=SAND,flex_gap=gap(26),css_classes='eola-reveal-section eola-essentials')
for c in summary['elements'][1]['elements'][1]['elements']:c['settings']['flex_gap']=gap(5)

feature_data=[('landscape','4.89 Acres','Space, privacy and versatility.','#land'),('hero','Custom Home','Thoughtfully designed living spaces with character and presence.','#home'),('barn','Barn + Workshop','Room for hobbies, projects, storage or lifestyle needs.','#workshop'),('terrace','Outdoor Living','Deck, pergola, grounds and room to enjoy the setting.','#outdoors')]
features=[]
for index,(key,title,copy,link) in enumerate(feature_data,1):
 features.append(col(title,[
 photo(key,title,220,height_mobile=size(220),link_to='custom',link={'url':link},image_border_radius=edges(22,22,0,0)),
 container(title+' content',[
 label(f'0{index} / DISCOVER',BLUE),heading(title,'h3',25),text(copy,n=13),
 button('Explore',link,outline=True,text_padding=edges(10,17,10,17),selected_icon={'value':'fas fa-arrow-right','library':'fa-solid'},icon_align='right',icon_indent=14)
 ],padding=edges(24),min_height=size(245),flex_gap=gap(14),flex_justify_content='space-between')
 ],23.4,css_classes='eola-feature-card',flex_gap=gap(0),width_tablet=size(48,'%'),border_radius=edges(24),overflow='hidden',background_background='classic',background_color='#F3F7FD',background_hover_background='classic',background_hover_color=ICE,background_hover_transition=size(.35,'s'),border_border='solid',border_width=edges(1),border_color='#D5E2F7',box_shadow_hover_box_shadow_type='yes',box_shadow_hover_box_shadow={'horizontal':0,'vertical':20,'blur':38,'spread':-16,'color':'#06265A38'}))
highlights=section('01 - Why this property stands out',[
 *center([label('HOME, LAND, LIFESTYLE & POSSIBILITY'),heading('More than a<br>beautiful home.',n=50)]),
 text('Set on nearly five acres, this property brings together a distinctive residence, usable land, outdoor living, workshop/studio space, barn amenities and a peaceful setting.',align='center',_element_width='initial',_element_custom_width=size(740),_element_custom_width_mobile=size(100,'%'),_align_self='center'),
 row('Four feature cards',features,padding=edges(22,0,0,0),flex_wrap='wrap',flex_gap=gap(20))
],'property')

home=section('02 - The home',[
 row('Editorial home composition',[
 col('Living room photograph',[photo('living','Main living room',620,height_mobile=size(380),_css_classes='eola-scene-home')],47,css_classes='eola-image-reveal'),
 col('Home story and details',[
 label('01 / THE HOME'),heading('Designed to<br>live beautifully.',n=48),text('A home with architectural character, generous living spaces and a setting that feels removed from the everyday while remaining connected to Oregon wine-country living.'),button('Explore the full photo gallery ↗','#gallery'),
 row('Kitchen and primary suite',[col('Kitchen',[photo('kitchen','Kitchen',200,height_mobile=size(170)),label('KITCHEN',MUTED)],48),col('Primary suite',[photo('bedroom','Primary suite',200,height_mobile=size(170)),label('PRIMARY SUITE',MUTED)],48)],flex_direction_mobile='row',padding=edges(20,0,0,0))
 ],47,flex_justify_content='center')
 ]),
 row('Architectural detail',[col('Architectural detail caption',[heading('Architectural<br>interior detail','h3',38),text('Thoughtfully designed living spaces with character and presence.')],47,flex_justify_content='center'),col('Detail image',[photo('detail','Architectural interior detail',280,height_mobile=size(220))],47)],padding=edges(10,0,0,0))
],'home')

land=section('03 - The land',[
 container('Land - immersive landscape',[
 button('4.89 acres · Oregon wine country','#details',True,_css_classes='eola-land-badge'),
 row('Land story',[col('Land breathing space',[],37,hide_mobile='hidden-mobile'),card('Nearly five acres',[
 label('02 / THE LAND',ICE),heading('Nearly five acres<br>of possibility.',n=46,color=WHITE),text('Open space, mature landscaping, natural rock features and usable acreage create opportunities for recreation, gardening, animals, hobbies or simply more room to breathe.','#DCE9FF'),text('4.89 acres · Barn · Workshop/studio<br>RV capability · Mature grounds',ICE,12)
 ],width=size(55,'%'),width_mobile=size(100,'%'),background_color='#06265AE6',css_classes='eola-glass-card')])
 ],**background('landscape','#06265A11'),min_height=size(600),min_height_mobile=size(600),padding=edges(36),padding_mobile=edges(20),border_radius=edges(28),flex_justify_content='space-between',css_classes='eola-land-scene')
],'land',padding=edges(25,30,50,30),padding_mobile=edges(16,14,30,14))

work=section('04 - Barn and workshop',[
 row('Barn and workshop introduction',[col('Heading',[label('03 / BARN + WORKSHOP'),heading('Space for what<br>matters to you.')],55),card('Barn and workshop highlighted note',[heading('Barn + workshop.','h3',30),text('Room for hobbies, projects, storage or lifestyle needs.',INK,14)],width=size(36,'%'),width_mobile=size(100,'%'),background_color=ICE,padding=edges(28),flex_justify_content='center')]),
 row('Barn and workshop cards',[
 col('Barn',[photo('barn','Barn',380),row('Barn title',[heading('Barn','h3',25),label('01')]),text('Flexible space for storage, equipment, animals or property use.')],47,css_classes='eola-lift-card'),
 col('Workshop / Studio',[photo('workshop','Workshop / studio',380),row('Workshop title',[heading('Workshop / studio','h3',25),label('02')]),text('A dedicated space for woodworking, creative work, projects or serious hobbies.')],47,margin=edges(65,0,0,0),margin_mobile=edges(0),css_classes='eola-lift-card')
 ],padding=edges(20,0,0,0))
],'workshop')

outdoor=section('05 - Outdoor lifestyle',[
 row('Outdoor lifestyle composition',[
 col('Outdoor photograph',[photo('terrace','Deck and pergola',580,height_mobile=size(370),_css_classes='eola-scene-outdoors')],43),
 col('Outdoor story',[label('04 / OUTDOOR LIFESTYLE'),heading('Step Outside<br>and Stay Awhile',n=54),text('The outdoor spaces create a natural extension of the home, with room to relax, entertain and take in the peaceful surroundings.'),card('Outdoor amenities',[label('OUTDOOR LIFESTYLE',INK),heading('Deck | Pergola','h3',29),text('Deck · Pergola · Mature Trees<br>Private Setting · Scenic Surroundings',INK,13)],background_color=ICE),button('Explore the Full Photo Gallery ↗','#gallery',outline=True)],50,flex_justify_content='center')
 ])
],'outdoors')
video=section('06 - Property experience',[
 row('Experience heading',[col('Property experience title',[label('PROPERTY VIDEO'),heading('Experience<br>the property.')],60),col('Tour invitation',[text('See why this property feels different in person.'),button('Schedule your private tour ↗',tour)],34,flex_justify_content='flex-end')]),
 widget('image-carousel','Interactive property preview',carousel=[media(key) for key in ['hero','living','kitchen','landscape','terrace']],thumbnail_size='full',slides_to_show='1',slides_to_scroll='1',navigation='both',autoplay='no',pause_on_hover='yes',pause_on_interaction='yes',infinite='yes',effect='slide',speed=800,show_caption='none',arrows_position='inside',arrows_color=WHITE,arrows_size=size(28),dots_position='outside',dots_color=BLUE,image_spacing='custom',image_spacing_custom=size(0),eola_image_height=size(560),eola_image_height_mobile=size(320),image_border_radius=edges(28),_css_classes='eola-property-carousel'),
 label('PROPERTY VIDEO COMING SOON',MUTED,align='center')
],'film')

labels=['Exterior','Kitchen','Living room','Primary suite','Deck','Grounds','Barn','Workshop','Acreage','Aerial']
keys=['hero','kitchen','living','bedroom','terrace','landscape','barn','workshop','landscape','hero']
categories=['home','home','home','home','grounds','grounds','spaces','spaces','grounds','grounds']
tiles=[]
for name,key,cat in zip(labels,keys,categories):
 tiles.append(col(name,[photo(key,name,250,link_to='file',open_lightbox='yes'),row(name+' caption',[heading(name,'h3',19,serif=False),label('↗',INK)])],31.8,width_tablet=size(48,'%'),css_classes='eola-gallery-tile eola-category-'+cat,flex_gap=gap(12)))
tiles[-1]['settings'].update(width=size(100,'%'),width_tablet=size(100,'%'))
tiles[-1]['elements'][0]['settings']['height']=size(360)
filters=row('Gallery filters',[button(title,'#gallery',outline=True,text_padding=edges(12,22,12,22),border_color='#C8D9F2',_css_classes='eola-filter eola-filter-'+key) for title,key in [('All views','all'),('The home','home'),('Outdoors','grounds'),('Barn & studio','spaces')]],flex_wrap='wrap',flex_direction_mobile='row',flex_gap=gap(10),flex_justify_content='center')
gallery=section('07 - Photo gallery',[
 *center([label('PHOTO GALLERY'),heading('Explore the property.')]),filters,
 row('Interactive photo gallery',tiles,flex_wrap='wrap',flex_gap=gap(26),flex_justify_content='flex-start',css_classes='eola-gallery-grid',padding=edges(22,0,0,0))
],'gallery')

facts_cards=[]
for k,v in [('Price','$1,499,000'),('Bedrooms','3'),('Bathrooms','3'),('Approx. Living Area','Approx. 3,112 sq ft'),('Lot','4.89 acres'),('Address','9055 SE Eola Hills Rd,<br>Amity, OR')]:
 facts_cards.append(card(k,[label(k.upper(),MUTED),heading(v,'p',24,serif=False)],width=size(48,'%'),width_mobile=size(100,'%'),padding=edges(24),flex_gap=gap(14)))
facts=section('08 - Property details',[
 row('At a glance',[col('Property details heading',[label('PROPERTY DETAILS'),heading('Property<br>at a glance.',n=50),text('9055 SE Eola Hills Rd<br>Amity, Oregon'),button('Contact the Listing Team ↗',tour,outline=True)],34,flex_justify_content='center'),col('Facts',[row('Property details cards',facts_cards,flex_wrap='wrap',flex_gap=gap(18))],61)])
],'details')
team=section('09 - Listing team',[
 container('Listing team - inset blue panel',[
 row('Meet the team',[
 col('Team introduction',[label('MEET THE LISTING TEAM',ICE),heading('Private tours &<br>property information.',n=50,color=WHITE),text('Wine Country Properties + eXp.','#DCE9FF',13)],31,flex_justify_content='center'),
 row('Agents',[
 card('Linda Lindsay',[
 row('Linda monogram and role',[container('Linda monogram',[heading('LL','p',31,BLUE,align='center')],width=size(72),min_height=size(72),flex_justify_content='center',background_background='classic',background_color=ICE,border_radius=edges(100)),label('01 / LISTING<br>AGENT',BLUE,typography_font_size=size(9))],flex_direction_mobile='row',flex_align_items='center'),
 container('Linda introduction',[heading('Linda Lindsay','h3',25),text('Broker & Realtor',BLUE,13)],flex_gap=gap(8)),
 container('Linda contact options',[button('(503) 720-1805','tel:+15037201805',outline=True,align='justify',text_padding=edges(15,16,15,16),selected_icon={'value':'fas fa-phone-alt','library':'fa-solid'},icon_align='left',icon_indent=10),button('Email Linda','mailto:linda.lindsay@exprealty.com',align='justify',text_padding=edges(15,16,15,16),selected_icon={'value':'fas fa-arrow-right','library':'fa-solid'},icon_align='right',icon_indent=14)],flex_gap=gap(10))
 ],width=size(48,'%'),width_mobile=size(100,'%'),background_color=WHITE,padding=edges(28),flex_gap=gap(26),flex_justify_content='space-between',css_classes='eola-lift-card eola-agent-card'),
 card('Anita Borgeas',[
 row('Anita monogram and role',[container('Anita monogram',[heading('AB','p',31,WHITE,align='center')],width=size(72),min_height=size(72),flex_justify_content='center',background_background='classic',background_color=BLUE,border_radius=edges(100)),label('02 / PRINCIPAL<br>BROKER',BLUE,typography_font_size=size(9))],flex_direction_mobile='row',flex_align_items='center'),
 container('Anita introduction',[heading('Anita Borgeas','h3',25),text('Principal Broker',BLUE,13)],flex_gap=gap(8)),
 container('Anita contact options',[text('Private Tours &<br>Property Information',INK,13),button('Contact the Listing Team',tour,align='justify',text_padding=edges(15,16,15,16),selected_icon={'value':'fas fa-arrow-right','library':'fa-solid'},icon_align='right',icon_indent=14)],flex_gap=gap(12))
 ],width=size(48,'%'),width_mobile=size(100,'%'),background_color=ICE,padding=edges(28),flex_gap=gap(26),flex_justify_content='space-between',css_classes='eola-lift-card eola-agent-card')
 ],width=size(65,'%'),width_mobile=size(100,'%'),flex_gap=gap(20))
 ])
 ],background_background='classic',background_color=INK,border_radius=edges(28),padding=edges(48),padding_tablet=edges(28),padding_mobile=edges(24))
],'team',padding=edges(42,24,42,24),padding_mobile=edges(24,12,24,12))
closing=section('10 - Your next chapter',[
 container('Closing invitation',[
 *center([label('9055 SE EOLA HILLS RD / AMITY, OREGON',ICE),heading('Could this be the property<br>you’ve been waiting for?',n=50,color=WHITE),text('Some homes offer space. Others offer lifestyle. This one offers both.','#EEF4FF')]),
 row('Final actions',[button('Schedule a private showing ↗',tour,True),button('Contact the listing team','#team',True,True)],flex_justify_content='center',flex_gap=gap(14))
 ],**background('landscape','#061D49CF'),border_radius=edges(28),padding=edges(90,40,90,40),padding_mobile=edges(55,24,55,24),flex_gap=gap(28))
],padding=edges(20,24,40,24),padding_mobile=edges(12,12,32,12))
footer=section('Footer - editable branding',[
 row('Footer details',[col('Brand',[heading('Wine Country','p',26),label('PROPERTIES + eXp',MUTED),text('9055 SE Eola Hills Rd · Amity, Oregon',n=12)],50),col('Footer details',[text('Linda Lindsay · Anita Borgeas<br>Equal Housing Opportunity',n=12),button('Back to top ↑','#top',outline=True,text_padding=edges(12,22,12,22))],34)]),

],html_tag='footer',padding=edges(22,48,45,48),padding_mobile=edges(24))
skip=button('Skip to property','#main-content',_css_classes='eola-skip')
hero['settings'].update(margin=edges(0),margin_mobile=edges(0))
nav['elements'][0]['settings']['width_tablet']=size(40,'%')
nav['elements'][1]['settings']['width_tablet']=size(55,'%')
nav['elements'][2]['settings']['hide_tablet']='hidden-tablet'
team['elements'][0]['elements'][0]['settings']['flex_direction_tablet']='column'
for team_column in team['elements'][0]['elements'][0]['elements']:
 team_column['settings']['width_tablet']=size(100,'%')
for interior_column in home['elements'][0]['elements'][1]['elements'][4]['elements']:
 interior_column['settings']['width_mobile']=size(47,'%')
for tile in tiles[:-1]:
 tile['settings']['width_mobile']=size(47,'%')
 tile['elements'][0]['settings']['height_mobile']=size(160)
 tile['elements'][1]['settings'].update(flex_direction_mobile='row',flex_align_items='center')
 tile['elements'][1]['elements'][0]['settings']['typography_font_size_mobile']=size(15)
tiles[-1]['elements'][0]['settings']['height_mobile']=size(220)
gallery['elements'][-1]['settings']['flex_gap_mobile']=gap(16)
hero_frame=container('Hero frame',[hero],padding=edges(18,18,0,18),padding_mobile=edges(10,10,0,10))
template={'title':'Eola Hills — Immersive Property Landing Page','type':'page','version':'0.4','page_settings':{'template':'elementor_canvas','hide_title':'yes','background_background':'classic','background_color':PAPER},'content':[skip,header,hero_frame,summary,highlights,home,land,work,outdoor,video,gallery,facts,team,closing,footer]}
(PLUGIN/'templates/property.json').write_text(json.dumps(template,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
print(f'Generated {counter} native Elementor elements.')
