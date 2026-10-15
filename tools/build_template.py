"""Generate the native Elementor Eola Hills editorial property landing page."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'wordpress/wp-content/themes/eola-hills'
INK='#25232B'; PAPER='#FFFFFF'; SAND='#F5F5F8'; BLUE='#4B5598'; GREEN='#55A044'; MUTED='#64636D'; WHITE='#FFFFFF'; ICE='#ECEEF8'
CLIENT_MEDIA=json.loads((PLUGIN/'assets/media/manifest.json').read_text(encoding='utf-8'))
counter=0
def ident():
 global counter
 counter+=1
 return hashlib.sha1(f'eola-v2-{counter}'.encode()).hexdigest()[:8]
def size(n,unit='px'):return {'unit':unit,'size':n,'sizes':[]}
def edges(t=0,r=None,b=None,l=None):return {'unit':'px','top':str(t),'right':str(t if r is None else r),'bottom':str(t if b is None else b),'left':str(t if l is None else l),'isLinked':False}
def gap(n):return {'unit':'px','size':n,'column':str(n),'row':str(n),'isLinked':True}
def media(key):return {'url':f'{{{{EOLA_ASSETS}}}}/media/{CLIENT_MEDIA[key]["file"]}','id':0}
def widget(kind,name,**s):return {'id':ident(),'elType':'widget','widgetType':kind,'settings':{'_title':name,**s},'elements':[]}
def heading(copy,tag='h2',n=48,color=INK,serif=True,**s):
 return widget('heading',copy.replace('<br>',' '),**{'title':copy,'header_size':tag,'title_color':color,'typography_typography':'custom','typography_font_family':'Eola Display' if serif else 'Eola Sans','typography_font_size':size(n),'typography_font_size_tablet':size(min(n,48)),'typography_font_size_mobile':size(min(n,38)),'typography_font_weight':'600' if serif or tag in ('h1','h2','h3') else '500','typography_line_height':size(1.12,'em'),'typography_letter_spacing':size(-1 if n>40 else 0),**s})
def label(copy,color=BLUE,**s):return heading(copy,'p',11,color,False,**{'typography_letter_spacing':size(1.8),'typography_line_height':size(1.5,'em'),**s})
def text(copy,color=MUTED,n=15,**s):return widget('text-editor',copy[:40],**{'editor':f'<p>{copy}</p>','text_color':color,'typography_typography':'custom','typography_font_family':'Eola Sans','typography_font_size':size(n),'typography_line_height':size(1.5,'em'),**s})
def button(copy,url,light=False,outline=False,**s):
 color=WHITE if light else INK
 return widget('button',copy,**{'text':copy,'link':{'url':url},'align':'left','button_text_color':color if outline else (INK if light else WHITE),'background_color':'#FFFFFF00' if outline else (WHITE if light else BLUE),'button_background_hover_color':ICE,'hover_color':INK,'border_border':'solid','border_width':edges(1),'border_color':color if outline else '#FFFFFF00','border_radius':edges(50),'text_padding':edges(17,26,17,26),'typography_typography':'custom','typography_font_family':'Eola Sans','typography_font_size':size(12),'typography_font_weight':'500',**s})
def container(name,children,**s):return {'id':ident(),'elType':'container','isInner':False,'settings':{'_title':name,'content_width':'full','flex_direction':'column','flex_gap':gap(16),'padding':edges(0),'flex_align_items':'stretch',**s},'elements':children}
def row(name,children,**s):return container(name,children,**{'flex_direction':'row','flex_direction_mobile':'column','flex_justify_content':'space-between',**s})
def col(name,children,width=48,**s):return container(name,children,**{'width':size(width,'%'),'width_mobile':size(100,'%'),**s})
def section(name,children,anchor=None,**s):return container(name,children,**{'html_tag':'section','content_width':'boxed','boxed_width':size(1200),'padding':edges(76,48,76,48),'padding_tablet':edges(56,28,56,28),'padding_mobile':edges(42,22,42,22),'css_classes':'eola-reveal-section',**({'_element_id':anchor} if anchor else {}),**s})
def photo(key,title,height=380,**s):return widget('image',title,**{'image':media(key),'image_size':'full','width':size(100,'%'),'height':size(height),'height_mobile':size(280),'object-fit':'cover','caption_source':'none','image_border_radius':edges(22),**s})
def background(key,overlay='#25232B88'):
 return {'background_background':'classic','background_image':media(key),'background_position':'center center','background_size':'cover','background_overlay_background':'classic','background_overlay_color':overlay,'background_overlay_opacity':size(1)}
def card(name,children,**s):return container(name,children,**{'background_background':'classic','background_color':SAND,'border_radius':edges(24),'padding':edges(28),'css_classes':'eola-lift-card',**s})
def center(items):
 for i in items:i['settings']['align']='center'
 return items
def logo(reverse=False,width=290):
 return widget('image','Wine Country Properties Group + eXp logo',image=media('logo-reverse' if reverse else 'logo'),image_size='full',width=size(width),width_mobile=size(260),align='left',link_to='custom',link={'url':'#top'})

tour='mailto:linda.lindsay@exprealty.com?subject=Private%20tour%20-%209055%20SE%20Eola%20Hills%20Rd'
nav=row('Header - editable brand and navigation',[
 col('Wine Country Properties Group + eXp',[logo()],33.33,flex_gap=gap(4)),
 row('Navigation',[button('The property','#property',outline=True,_css_classes='eola-nav-link'),button('Gallery','#gallery',outline=True,_css_classes='eola-nav-link'),button('The team','#team',outline=True,_css_classes='eola-nav-link')],width=size(43,'%'),width_mobile=size(100,'%'),flex_direction_mobile='row',flex_wrap='wrap',flex_justify_content='center',flex_justify_content_mobile='flex-start',flex_gap=gap(8)),
 col('Header tour action',[button('Schedule a Private Tour ↗',tour)],33.33,flex_align_items='flex-end',hide_mobile='hidden-mobile')
],flex_gap=gap(0),flex_gap_mobile=gap(18),flex_align_items='center',flex_align_items_mobile='flex-start',css_classes='eola-header',_element_id='top')
nav['elements'][1]['settings']['width']=size(33.33,'%')
for w in nav['elements'][1]['elements']:
 w['settings'].update(text_padding=edges(12,16,12,16),border_width=edges(0),typography_font_size=size(12))
header=section('Header',[nav],html_tag='header',padding=edges(24,48,24,48),padding_tablet=edges(22,28,22,28),padding_mobile=edges(22,28,12,28),css_classes='eola-site-header')
hero_copy=col('Hero introduction',[
 button('9055 SE EOLA HILLS RD','#details',True,True,text_padding=edges(12,18,12,18),typography_font_size=size(10),typography_letter_spacing=size(1.2),border_radius=edges(999),border_color='#FFFFFF80',background_color='#25232B66',selected_icon={'value':'fas fa-bullseye','library':'fa-solid'},icon_align='left',icon_indent=10,_css_classes='eola-address-pill'),
 heading('Unmistakably<br>special.','h1',78,WHITE,typography_font_size_tablet=size(60),typography_font_size_mobile=size(37),typography_line_height=size(1.08,'em')),
 text('A rare combination of home, land,<br>lifestyle & possibility.','#F6F5FB',17),
 row('Hero actions',[button('Schedule a private tour ↗',tour,True),button('View property highlights ↓','#property',True,True)],flex_justify_content='flex-start',flex_wrap='wrap',flex_gap=gap(12))
],62,css_classes='eola-hero-copy',flex_gap=gap(19))
offer=card('Property offering',[
 label('RMLS #644545554',BLUE),heading('$1,499,000','p',34,serif=False,typography_font_size_mobile=size(30)),
 text('3 Beds · 3 Baths<br>Approx. 3,018 Sq Ft',INK,14),
 button('Property details ↗','#details',outline=True,text_padding=edges(12,18,12,18))
],width=size(30,'%'),width_mobile=size(100,'%'),background_color=PAPER,padding=edges(28),padding_mobile=edges(24),border_radius=edges(24),flex_gap=gap(18),css_classes='eola-hero-offer')
scenes=row('Hero photo selector',[button(copy,'#main-content',True,True,text_padding=edges(9,18,9,18),border_color='#FFFFFF55',_css_classes='eola-scene-button eola-scene-'+key) for copy,key in [('01 / Exterior','exterior'),('02 / Interiors','interior'),('03 / The land','land')]],flex_direction_mobile='row',flex_wrap='wrap',flex_justify_content='flex-start',flex_gap=gap(8),padding=edges(8,0,0,0))
hero=section('Hero - cinematic property introduction',[
 button('AMITY, OREGON  ·  4.89 ACRES','#land',True,text_padding=edges(13,20,13,20),typography_font_size=size(10),typography_letter_spacing=size(1)),
 container('Photograph breathing space',[],min_height=size(75),min_height_mobile=size(35)),
 row('Headline and property offering',[hero_copy,offer],flex_align_items='flex-end',flex_align_items_mobile='stretch',flex_gap=gap(30)),
 scenes
],anchor='main-content',**{k:v for k,v in background('hero').items() if k not in ['background_overlay_background','background_overlay_color']},background_overlay_background='gradient',background_overlay_color='#262A5005',background_overlay_color_b='#262A50F5',background_overlay_color_stop=size(0,'%'),background_overlay_color_b_stop=size(100,'%'),background_overlay_gradient_angle=size(180,'deg'),padding=edges(32,48,32,48),padding_tablet=edges(28),padding_mobile=edges(24),border_radius=edges(28),boxed_width=size(1260),min_height=size(720),min_height_mobile=size(0),flex_gap=gap(18),flex_justify_content='space-between',css_classes='eola-immersive-hero')

stats=[]
for title,value,icon in [('BEDROOMS','3','bed'),('BATHROOMS','3','bath'),('APPROX. SQ FT','3,018','ruler-combined')]:
 stats.append(col(title,[
 widget('icon',title+' icon',selected_icon={'value':'fas fa-'+icon,'library':'fa-solid'},view='stacked',shape='square',primary_color=ICE,secondary_color=BLUE,size=size(19),icon_padding=size(14),border_radius=edges(13),align='left'),
 heading(value,'p',48,serif=False,_css_classes='eola-stat-count',typography_font_weight='600'),label(title,INK,typography_letter_spacing=size(.5))
 ],23,background_background='classic',background_color=WHITE,padding=edges(38,30,34,30),padding_tablet=edges(28,18,28,18),padding_mobile=edges(28,24,28,24),width_mobile=size(100,'%'),flex_gap=gap(15),border_border='solid',border_width=edges(0,0,0,1),border_width_mobile=edges(1,0,0,0),border_color='#DDDDE8'))
summary=section('Property essentials',[
 row('Essentials introduction',[
 col('Introduction',[label('PROPERTY DETAILS'),heading('Property at a Glance',n=40,serif=False,typography_font_size_mobile=size(30))],72),
 button('Explore the property','#details',outline=True,text_padding=edges(14,20,14,20),selected_icon={'value':'fas fa-arrow-right','library':'fa-solid'},icon_align='right',icon_indent=15)
 ],flex_align_items='center',flex_align_items_mobile='flex-start'),
 container('Essentials connected panel',[
 row('Essential numbers',[
 col('Acreage feature',[
 label('OREGON WINE COUNTRY',ICE,typography_font_size=size(10)),heading('4.89','p',76,WHITE,False,typography_font_weight='600',typography_font_size_mobile=size(64)),heading('Acres','h3',21,WHITE,False),text('Space, privacy and versatility.',WHITE,12)
 ],31,width_mobile=size(100,'%'),padding=edges(38,34,34,34),padding_tablet=edges(28,24,28,24),background_background='gradient',background_color='#262A50',background_color_b='#4B5598',background_gradient_angle=size(35,'deg'),flex_gap=gap(18),css_classes='eola-acreage-feature'),
 *stats
 ],flex_gap=gap(0),css_classes='eola-facts-strip'),
 row('Property essentials footer',[
 col('Location',[label('AMITY, OREGON',INK),text('9055 SE Eola Hills Rd',n=12)],34),
 col('Property character',[label('HOME, LAND, LIFESTYLE & POSSIBILITY',INK),text('A rare combination of home, land, lifestyle & possibility.',n=12)],34),
 col('Listing representation',[label('RMLS #644545554',INK),text('Wine Country Properties Group | eXp Realty',n=12)],32)
 ],padding=edges(24,30,24,30),background_background='classic',background_color=WHITE,flex_gap=gap(24),border_border='solid',border_width=edges(1,0,0,0),border_color='#DDDDE8')
 ],border_border='solid',border_width=edges(3,1,1,1),border_color=GREEN,border_radius=edges(24),overflow='hidden',flex_gap=gap(0))
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
 ],23.4,css_classes='eola-feature-card',flex_gap=gap(0),width_tablet=size(48,'%'),border_radius=edges(24),overflow='hidden',background_background='classic',background_color='#F5F5F8',background_hover_background='classic',background_hover_color=ICE,background_hover_transition=size(.35,'s'),border_border='solid',border_width=edges(1),border_color='#DDDDE8',box_shadow_hover_box_shadow_type='yes',box_shadow_hover_box_shadow={'horizontal':0,'vertical':20,'blur':38,'spread':-16,'color':'#25232B38'}))
highlights=section('01 - Why this property stands out',[
 *center([label('HOME, LAND, LIFESTYLE & POSSIBILITY'),heading('More than a<br>beautiful home.',n=50)]),
 text('Set on nearly five acres, this property brings together a distinctive residence, usable land, outdoor living, workshop/studio space, barn amenities and a peaceful setting.',align='center',_element_width='initial',_element_custom_width=size(740),_element_custom_width_mobile=size(100,'%'),_align_self='center'),
 row('Four feature cards',features,padding=edges(22,0,0,0),flex_wrap='wrap',flex_gap=gap(20))
],'property')

home=section('02 - The home',[
 row('Editorial home composition',[
 col('Living room photograph',[photo('living','Main living room',620,height_mobile=size(380),_css_classes='eola-scene-home')],47,css_classes='eola-image-reveal'),
 col('Home story and details',[
 label('01 / THE HOME'),heading('Designed to<br>live beautifully.',n=48),text('Inside, a soaring foyer, dramatic upper catwalk, oak hardwood floors with decorative inlay, custom millwork and abundant natural light set the tone. The two-story living room showcases a floor-to-ceiling stacked-stone gas fireplace and architectural window wall overlooking the grounds, with doors opening to the expansive covered deck and BBQ area.'),button('Explore the full photo gallery ↗','#gallery'),
 row('Kitchen and primary suite',[col('Kitchen',[photo('kitchen','Kitchen',200,height_mobile=size(170)),label('KITCHEN',MUTED)],48),col('Primary suite',[photo('bedroom','Primary suite',200,height_mobile=size(170)),label('PRIMARY SUITE',MUTED)],48)],flex_direction_mobile='row',padding=edges(20,0,0,0))
 ],47,flex_justify_content='center')
 ]),
 row('Architectural detail',[col('Architectural detail caption',[heading('Architectural<br>interior detail','h3',38),text('The well-appointed kitchen features a large center island, extensive cabinetry, walk-in pantry, KitchenAid appliances, five-burner gas cooktop with retractable downdraft ventilation and a window wall capturing territorial and Mt. Hood views.')],47,flex_justify_content='center'),col('Detail image',[photo('detail','Architectural interior detail',280,height_mobile=size(220))],47)],padding=edges(10,0,0,0))
],'home')

land=section('03 - The land',[
 container('Land - immersive landscape',[
 button('4.89 acres · Oregon wine country','#details',True,_css_classes='eola-land-badge'),
 row('Land story',[col('Land breathing space',[],37,hide_mobile='hidden-mobile'),card('Nearly five acres',[
 label('02 / THE LAND',ICE),heading('Nearly five acres<br>of possibility.',n=46,color=WHITE),text('Open space, mature landscaping, natural rock features and usable acreage create opportunities for recreation, gardening, animals, hobbies or simply more room to breathe.','#ECEEF8'),text('4.89 acres · Barn · Workshop/studio<br>RV capability · Mature grounds',ICE,12)
 ],width=size(55,'%'),width_mobile=size(100,'%'),background_color='#25232BE6',css_classes='eola-glass-card')])
 ],**background('landscape','#25232B11'),min_height=size(600),min_height_mobile=size(600),padding=edges(36),padding_mobile=edges(20),border_radius=edges(28),flex_justify_content='space-between',css_classes='eola-land-scene')
],'land',padding=edges(25,30,50,30),padding_mobile=edges(16,14,30,14))

work=section('04 - Barn and workshop',[
 row('Barn and workshop introduction',[col('Heading',[label('03 / BARN + WORKSHOP'),heading('Space for what<br>matters to you.')],55),card('Barn and workshop highlighted note',[heading('Barn + workshop.','h3',30),text('Room for hobbies, projects, storage or lifestyle needs.',INK,14)],width=size(36,'%'),width_mobile=size(100,'%'),background_color=ICE,padding=edges(28),flex_justify_content='center')]),
 row('Barn and workshop cards',[
 col('Barn',[photo('barn','Barn',380),row('Barn title',[heading('Barn','h3',25),label('01')]),text('The 2,304 SF barn is set on a 48’ x 48’ concrete pad and features two 12’ x 12’ stalls, seven Dutch doors, hay loft, large sliding doors and covered side aisles. Equipped with power and water, the barn also includes a bathroom with shower and a workshop area with refrigerator, counter space and built-in shelving.')],47,css_classes='eola-lift-card'),
 col('Workshop / Studio',[photo('workshop','Workshop / studio',380),row('Workshop title',[heading('Workshop / studio','h3',25),label('02')]),text('The heated 435 SF building offers 11’ ceilings, commercial-grade flooring, full-light French doors, custom wood counters and cabinetry, pull-out workspace, lumber rack, compressed-air quick-connect fittings, dedicated electrical panel and abundant natural light.')],47,margin=edges(65,0,0,0),margin_mobile=edges(0),css_classes='eola-lift-card')
 ],padding=edges(20,0,0,0))
],'workshop')

outdoor=section('05 - Outdoor lifestyle',[
 row('Outdoor lifestyle composition',[
 col('Outdoor photograph',[photo('terrace','Deck and pergola',580,height_mobile=size(370),_css_classes='eola-scene-outdoors')],43),
 col('Outdoor story',[label('04 / OUTDOOR LIFESTYLE'),heading('Step Outside<br>and Stay Awhile',n=54),text('Professionally landscaped and irrigated grounds, a custom water feature with natural boulders and architectural stone centerpiece, expansive covered rear deck and covered outdoor fire pit create inviting spaces to enjoy the quiet Wine Country setting.'),card('Outdoor amenities',[label('OUTDOOR LIFESTYLE',INK),heading('Deck | Pergola','h3',29),text('Deck · Pergola · Mature Trees<br>Private Setting · Scenic Surroundings',INK,13)],background_color=ICE),button('Explore the Full Photo Gallery ↗','#gallery',outline=True)],50,flex_justify_content='center')
 ])
],'outdoors')
video=section('06 - Property experience',[
 row('Experience heading',[col('Property experience title',[label('PROPERTY VIDEO'),heading('Experience<br>the property.')],60),col('Tour invitation',[text('See why this property feels different in person.'),button('Schedule your private tour ↗',tour)],34,flex_justify_content='flex-end')]),
 widget('image-carousel','Interactive property preview',carousel=[media(key) for key in ['hero','living','kitchen','landscape','terrace']],thumbnail_size='full',slides_to_show='1',slides_to_scroll='1',navigation='both',autoplay='no',pause_on_hover='yes',pause_on_interaction='yes',infinite='yes',effect='slide',speed=800,show_caption='none',arrows_position='inside',arrows_color=WHITE,arrows_size=size(28),dots_position='outside',dots_color=BLUE,image_spacing='custom',image_spacing_custom=size(0),eola_image_height=size(560),eola_image_height_mobile=size(320),image_border_radius=edges(28),_css_classes='eola-property-carousel'),
 label('PROPERTY VIDEO COMING SOON',MUTED,align='center')
],'film')

labels=['Exterior','Kitchen','Living room','Primary suite','Primary bath','Family room / den','Office','Covered deck','Grounds','Barn','Workshop','Garage & RV bay','Barn interior','Acreage','Aerial']
keys=['hero','kitchen','living','bedroom','primary-bath','family-room','office','terrace','grounds','barn','workshop','garage','barn-interior','acreage','aerial']
categories=['home']*7+['grounds','grounds','spaces','spaces','spaces','spaces','grounds','grounds']
tiles=[]
for name,key,cat in zip(labels,keys,categories):
 tiles.append(col(name,[photo(key,name,250,link_to='file',open_lightbox='yes'),row(name+' caption',[heading(name,'h3',19,serif=False),label('↗',INK)])],31.8,width_tablet=size(48,'%'),css_classes='eola-gallery-tile eola-category-'+cat,flex_gap=gap(12)))
tiles[-1]['settings'].update(width=size(100,'%'),width_tablet=size(100,'%'))
tiles[-1]['elements'][0]['settings']['height']=size(360)
filters=row('Gallery filters',[button(title,'#gallery',outline=True,text_padding=edges(12,22,12,22),border_color='#D1D0DE',_css_classes='eola-filter eola-filter-'+key) for title,key in [('All views','all'),('The home','home'),('Outdoors','grounds'),('Barn & studio','spaces')]],flex_wrap='wrap',flex_direction_mobile='row',flex_gap=gap(10),flex_justify_content='center')
gallery=section('07 - Photo gallery',[
 *center([label('PHOTO GALLERY'),heading('Explore the property.')]),filters,
 row('Interactive photo gallery',tiles,flex_wrap='wrap',flex_gap=gap(26),flex_justify_content='flex-start',css_classes='eola-gallery-grid',padding=edges(22,0,0,0))
],'gallery')

facts_cards=[]
for k,v in [('Price','$1,499,000'),('Bedrooms','3'),('Bathrooms','3'),('Approx. Living Area','Approx. 3,018 sq ft'),('Lot','4.89 acres'),('Address','9055 SE Eola Hills Rd,<br>Amity, OR'),('Year built','2000'),('RMLS #','644545554')]:
 facts_cards.append(card(k,[label(k.upper(),MUTED),heading(v,'p',24,serif=False)],width=size(48,'%'),width_mobile=size(100,'%'),padding=edges(24),flex_gap=gap(14)))
facts=section('08 - Property details',[
 row('At a glance',[col('Property details heading',[label('PROPERTY DETAILS'),heading('Property<br>at a glance.',n=50),text('9055 SE Eola Hills Rd<br>Amity, Oregon'),button('Contact the Listing Team ↗',tour,outline=True)],34,flex_justify_content='center'),col('Facts',[row('Property details cards',facts_cards,flex_wrap='wrap',flex_gap=gap(18))],61)])
],'details')
garage=section('Detached garage and RV bay',[
 row('Garage and RV bay composition',[
 col('Garage description',[label('DETACHED GARAGE & RV BAY'),heading('1,498 sq ft.<br>Garage & RV bay.',n=46),text('An impressive 1,498 SF detached structure includes an oversized 3-car garage plus a two-story-high RV bay capable of accommodating a 40\' RV. The RV bay has an indoor electrical hookup, while the exterior RV driveway provides an additional full RV hookup. A covered breezeway connects the garage to the home.'),button('View property details','#details',outline=True)],45,flex_justify_content='center'),
 col('Garage photograph',[photo('garage','Detached garage and RV bay',470,height_mobile=size(320))],49)
 ])
],'garage')

amenity_groups=[
 ('Home & systems',['Custom built in 2000 | 3,018 SqFt | 4.89 acres','3 bedrooms | 3 full baths | office | family room/den','Generator w/ generator panel','Electric furnace | heat-pump | 50-gallon water heater','Bedroom (main floor) & adjacent full bath (main floor)','Custom battery blinds w/ remote controls','Home alarm system | home vacuum system']),
 ('Kitchen & living spaces',['Large center island w/ storage seats four','Walk-in pantry w/ storage shelves','GE five-burner gas cooktop w/ Dacor retractable downdraft ventilation & safety lock','SS KitchenAid built-in convection oven','SS KitchenAid built-in dishwasher','SS KitchenAid 1,200-watt built-in microwave','Under-counter built-in ice maker','Floor-to-ceiling stacked-stone gas fireplace w/ custom wood mantel','Doors open to covered deck & BBQ area']),
 ('Primary suite, office & laundry',['Territorial & Mt. Hood views','Walk-in closet w/ built-in shelves & cedar shoe rack','Double sinks | Jacuzzi tub | tile floors','Walk-in shower w/ tile inlay surround','Built-in custom wood cabinets & drawers','Large built-in corner desk w/ window views facing east & west','Maytag washer (sold w/ home)','Maytag electric dryer (sold w/ home)','Whirlpool upright freezer (sold w/ home)','Dedicated work-hobby area w/ dual-window views']),
 ('Grounds & outdoor living',['Architectural tile roof | Cedar lap siding','Two-story stacked stone columns at entry','Circular driveway w/ finished concrete curbing','Covered breezeway connects home to garage','Covered deck expands across back side of home','Professionally landscaped grounds w/ irrigation','Custom water feature w/ natural boulder surround & dramatic architectural stone centerpiece','Covered outdoor fire pit']),
 ('Monitor-style barn',['Concrete pad | 48\' X 48\' | 2,304 SqFt','7 dutch doors | (2) 12\' x 12\' stalls | hay loft','Lg. sliding doors | covered side aisles','Metal roof | insulated ceiling','Equipped w/ power | water','Bathroom w/ sink | toilet | shower','Workshop w/ fridge & counter space','9 fluorescent lights & built-in shelving']),
 ('Detached workshop or flex space',['29\' x 15\' | 435 SqFt | 11\' ceilings','Power | heat | commercial-grade floors','Entrance: full-light French doors','Custom wood counters','Custom-built cabinets w/ industrial handles','Pull-out workspace | tool hanger | lumber rack','Equipped w/ 4 wall-mounted compressed-air quick-connect fittings','Workshop electrical panel | 240/20-amp outlet','3 Lg. vinyl windows | generous natural light'])
]
amenities=section('Confirmed property amenities',[
 row('Amenities introduction',[col('Amenities heading',[label('9055 SE EOLA HILLS RD'),heading('Amenities',n=48)],55),button('Download amenities (PDF)',media('amenities')['url'],outline=True,selected_icon={'value':'fas fa-download','library':'fa-solid'},icon_align='right',icon_indent=12)]),
 widget('accordion','Client-supplied amenities',tabs=[{'_id':ident(),'tab_title':title,'tab_content':'<ul>'+''.join('<li>'+item+'</li>' for item in items)+'</ul>'} for title,items in amenity_groups],title_html_tag='h3',border_width=size(1),border_color='#DDDDE8',title_background=WHITE,title_color=INK,tab_active_color=BLUE,content_background_color=WHITE,content_color=MUTED,title_typography_typography='custom',title_typography_font_family='Eola Sans',title_typography_font_weight='600',title_typography_font_size=size(18),title_typography_line_height=size(1.35,'em'),content_typography_typography='custom',content_typography_font_family='Eola Sans',content_typography_font_size=size(14),content_typography_line_height=size(1.65,'em'),title_padding=edges(24),content_padding=edges(10,28,24,28),_css_classes='eola-amenities')
],'amenities',background_background='classic',background_color=SAND)

plan_sheets=[(1,'1ST FLOOR · HOME, GARAGE & WORKSHOP','1st floor - home, garage & workshop'),(2,'2ND FLOOR','2nd floor'),(3,'BARN','Barn')]
plans=section('Floor plans',[
 row('Floor plan overview',[
 col('Floor plan description',[label('PROPERTY DETAILS'),heading('Floor plans',n=48),text('Approximate living area: 3,018 sq ft per the listing. Plans show the home, covered deck, detached 3-car garage, RV bay, woodworking shop and barn. Measurements are approximate.',n=13),button('View all floors',media('floorplan-4')['url'],outline=True)],39,flex_justify_content='center'),
 col('All plans',[photo('floorplan-4','All floors - floor plan overview',440,height_mobile=size(300),link_to='file',open_lightbox='yes',**{'object-fit':'contain'})],57)
 ]),
 row('Individual floor plans',[col('Plan '+str(i),[photo('floorplan-'+str(i),'Floor plan - '+title,250,height_mobile=size(240),link_to='file',open_lightbox='yes',**{'object-fit':'contain'}),label(name,BLUE)],31.5) for i,name,title in plan_sheets])
],'floor-plans')
team=section('09 - Listing team',[
 container('Listing team - inset brand panel',[
 row('Meet the team',[
 col('Team introduction',[label('MEET THE LISTING TEAM',ICE),heading('Private tours &<br>property information.',n=44,color=WHITE),logo(True,280)],31,flex_justify_content='center'),
 row('Agents',[
 card('Linda Lindsay',[
 row('Linda portrait and role',[photo('linda','Linda Lindsay',110,width=size(100),height_mobile=size(110),image_border_radius=edges(100),_css_classes='eola-linda-portrait'),label('01 / LISTING<br>AGENT',BLUE,typography_font_size=size(9))],flex_direction_mobile='row',flex_align_items='center'),
 container('Linda introduction',[heading('Linda Lindsay','h3',25),text('Broker & Realtor',BLUE,13),text('License #931000172',MUTED,11)],flex_gap=gap(8)),
 container('Linda contact options',[button('(503) 720-1805','tel:+15037201805',outline=True,align='justify',text_padding=edges(15,16,15,16),selected_icon={'value':'fas fa-phone-alt','library':'fa-solid'},icon_align='left',icon_indent=10),button('Email Linda','mailto:linda.lindsay@exprealty.com',align='justify',text_padding=edges(15,16,15,16),selected_icon={'value':'fas fa-arrow-right','library':'fa-solid'},icon_align='right',icon_indent=14)],flex_gap=gap(10))
 ],width=size(48,'%'),width_mobile=size(100,'%'),background_color=WHITE,padding=edges(28),flex_gap=gap(26),flex_justify_content='space-between',css_classes='eola-lift-card eola-agent-card'),
 card('Anita Borgaes',[
 row('Anita portrait and role',[photo('anita-contact','Anita Borgaes',160,width=size(320),height_mobile=size(160),image_border_radius=edges(0),_css_classes='eola-anita-portrait'),label('02 / PRINCIPAL<br>BROKER',BLUE,typography_font_size=size(9))],flex_direction_mobile='row',flex_align_items='center'),
 container('Anita introduction',[heading('Anita Borgaes','h3',25),text('Principal Broker & Realtor',BLUE,13),text('License #200211111',MUTED,11)],flex_gap=gap(8)),
 container('Anita contact options',[button('(503) 550-8050','tel:+15035508050',outline=True,align='justify',text_padding=edges(15,16,15,16),selected_icon={'value':'fas fa-phone-alt','library':'fa-solid'},icon_align='left',icon_indent=10),button('Email Anita','mailto:anita@wcpcompanies.com',align='justify',text_padding=edges(15,16,15,16),selected_icon={'value':'fas fa-arrow-right','library':'fa-solid'},icon_align='right',icon_indent=14)],flex_gap=gap(10))
 ],width=size(48,'%'),width_mobile=size(100,'%'),background_color=ICE,padding=edges(28),flex_gap=gap(26),flex_justify_content='space-between',css_classes='eola-lift-card eola-agent-card')
 ],width=size(65,'%'),width_mobile=size(100,'%'),flex_gap=gap(20))
 ])
 ],background_background='classic',background_color=BLUE,border_radius=edges(28),padding=edges(48),padding_tablet=edges(28),padding_mobile=edges(24))
],'team',padding=edges(42,24,42,24),padding_mobile=edges(24,12,24,12))
closing=section('10 - Your next chapter',[
 container('Closing invitation',[
 *center([label('9055 SE EOLA HILLS RD / AMITY, OREGON',ICE),heading('Could this be the property<br>you’ve been waiting for?',n=50,color=WHITE),text('Some homes offer space. Others offer lifestyle. This one offers both.','#F6F5FB')]),
 row('Final actions',[button('Schedule a private showing ↗',tour,True),button('Contact the listing team','#team',True,True)],flex_justify_content='center',flex_gap=gap(14))
 ],**background('landscape','#262A50CF'),border_radius=edges(28),padding=edges(90,40,90,40),padding_mobile=edges(55,24,55,24),flex_gap=gap(28))
],padding=edges(20,24,40,24),padding_mobile=edges(12,12,32,12))
footer=section('Footer - editable branding',[
 row('Footer details',[col('Brand',[logo(False,300),text('9055 SE Eola Hills Rd · Amity, Oregon<br>RMLS #644545554',n=12)],50),col('Footer details',[text('Linda Lindsay · License #931000172<br>Anita Borgaes · License #200211111<br>Equal Housing Opportunity',n=12),button('Back to top ↑','#top',outline=True,text_padding=edges(12,22,12,22))],40)]),

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
template={'title':'Eola Hills — Client Property Landing Page','type':'page','version':'0.4','page_settings':{'template':'elementor_canvas','hide_title':'yes','background_background':'classic','background_color':PAPER},'content':[skip,header,hero_frame,summary,highlights,home,land,work,garage,outdoor,video,gallery,facts,amenities,plans,team,closing,footer]}
(PLUGIN/'templates/property.json').write_text(json.dumps(template,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
def element_count(nodes):return sum(1+element_count(n['elements']) for n in nodes)
print(f'Generated {element_count(template["content"])} native Elementor elements.')
