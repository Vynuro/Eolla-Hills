"""Generate the native Elementor Eola Hills editorial property landing page."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
PLUGIN=ROOT/'wordpress/wp-content/plugins/eola-hills'
INK='#17212D'; PAPER='#FFFEFC'; SAND='#F1ECE5'; GOLD='#93683F'; MUTED='#656A72'; WHITE='#FFFFFF'; CHAMPAGNE='#E6C8A6'
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
def heading(copy,tag='h2',n=56,color=INK,serif=True,**s):
 return widget('heading',copy.replace('<br>',' '),**{'title':copy,'header_size':tag,'title_color':color,'typography_typography':'custom','typography_font_family':'Eola Display' if serif else 'Eola Sans','typography_font_size':size(n),'typography_font_size_tablet':size(min(n,48)),'typography_font_size_mobile':size(min(n,38)),'typography_font_weight':'400' if serif else '600','typography_line_height':size(1.05,'em'),'typography_letter_spacing':size(-1 if n>40 else 0),**s})
def label(copy,color=GOLD,**s):return heading(copy,'p',11,color,False,**{'typography_letter_spacing':size(1.8),'typography_line_height':size(1.5,'em'),**s})
def text(copy,color=MUTED,n=15,**s):return widget('text-editor',copy[:40],**{'editor':f'<p>{copy}</p>','text_color':color,'typography_typography':'custom','typography_font_family':'Eola Sans','typography_font_size':size(n),'typography_line_height':size(1.75,'em'),**s})
def button(copy,url,light=False,outline=False,**s):
 color=WHITE if light else INK
 return widget('button',copy,**{'text':copy,'link':{'url':url},'align':'left','button_text_color':color if outline else (INK if light else WHITE),'background_color':'#FFFFFF00' if outline else color,'button_background_hover_color':CHAMPAGNE,'hover_color':INK,'border_border':'solid','border_width':edges(1),'border_color':color if outline else '#FFFFFF00','border_radius':edges(50),'text_padding':edges(17,26,17,26),'typography_typography':'custom','typography_font_family':'Eola Sans','typography_font_size':size(12),'typography_font_weight':'600',**s})
def container(name,children,**s):return {'id':ident(),'elType':'container','isInner':False,'settings':{'_title':name,'content_width':'full','flex_direction':'column','flex_gap':gap(20),'padding':edges(0),'flex_align_items':'stretch',**s},'elements':children}
def row(name,children,**s):return container(name,children,**{'flex_direction':'row','flex_direction_mobile':'column','flex_justify_content':'space-between',**s})
def col(name,children,width=48,**s):return container(name,children,**{'width':size(width,'%'),'width_mobile':size(100,'%'),**s})
def section(name,children,anchor=None,**s):return container(name,children,**{'html_tag':'section','content_width':'boxed','boxed_width':size(1200),'padding':edges(76,48,76,48),'padding_tablet':edges(56,28,56,28),'padding_mobile':edges(42,22,42,22),'css_classes':'eola-reveal-section',**({'_element_id':anchor} if anchor else {}),**s})
def photo(key,title,height=380,**s):return widget('image',title,**{'image':media(key),'image_size':'full','width':size(100,'%'),'height':size(height),'height_mobile':size(280),'object-fit':'cover','caption_source':'none','image_border_radius':edges(22),**s})
def background(key,overlay='#17212D88'):
 return {'background_background':'classic','background_image':media(key),'background_position':'center center','background_size':'cover','background_overlay_background':'classic','background_overlay_color':overlay,'background_overlay_opacity':size(1)}
def card(name,children,**s):return container(name,children,**{'background_background':'classic','background_color':SAND,'border_radius':edges(24),'padding':edges(28),'css_classes':'eola-lift-card',**s})
def center(items):
 for i in items:i['settings']['align']='center'
 return items

tour='mailto:linda.lindsay@exprealty.com?subject=Private%20tour%20-%209055%20SE%20Eola%20Hills%20Rd'
nav=row('Header - editable brand and navigation',[
 col('Wine Country Properties + eXp',[heading('Wine Country','p',23,WHITE),label('PROPERTIES / eXp',CHAMPAGNE,typography_font_size=size(9))],26,flex_gap=gap(5)),
 row('Navigation',[button('The property','#property',True,True,_css_classes='eola-nav-link'),button('Gallery','#gallery',True,True,_css_classes='eola-nav-link'),button('The team','#team',True,True,_css_classes='eola-nav-link')],width=size(46,'%'),width_mobile=size(100,'%'),flex_direction_mobile='row',flex_wrap='wrap',flex_justify_content='center',flex_justify_content_mobile='flex-start',flex_gap=gap(8)),
 button('Book a private tour ↗',tour,True,hide_mobile='hidden-mobile')
],flex_align_items='center',flex_align_items_mobile='flex-start',padding=edges(0,0,10,0),css_classes='eola-header',_element_id='top')
for w in nav['elements'][1]['elements']:w['settings'].update(text_padding=edges(10,18,10,18),border_color='#FFFFFF55',typography_font_size=size(11))
hero_intro=row('Hero introduction',[
 col('Hero copy',[label('9055 SE EOLA HILLS RD · AMITY, OREGON',CHAMPAGNE),heading('Unmistakably<br>special.','h1',56,WHITE,typography_font_size_mobile=size(46)),text('A rare combination of home, land,<br>lifestyle & possibility.','#F3F0EB',15)],54,css_classes='eola-hero-copy',flex_gap=gap(14)),
 col('Hero side note',[text('OREGON WINE COUNTRY<br>A place to make your own.','#F3F0EB',11,align='right')],23,flex_justify_content='flex-start',hide_mobile='hidden-mobile')
],padding=edges(8,0,0,0),padding_mobile=edges(18,0,0,0))
hero_word=heading('EOLA HILLS','p',198,WHITE,typography_font_size_tablet=size(118),typography_font_size_mobile=size(60),typography_line_height=size(.9,'em'),typography_font_weight='300',typography_letter_spacing=size(-5),typography_letter_spacing_mobile=size(-2),align='center',_css_classes='eola-hero-word')
hero_bottom=row('Hero - invitation and floating property cards',[
 col('Private showing invitation',[button('Schedule a private tour ↗',tour,True),button('View property highlights ↓','#property',True,True)],43,flex_justify_content='flex-end',flex_gap=gap(14)),
 row('Floating facts',[
 card('Offering',[label('OFFERED AT',CHAMPAGNE),heading('$1,499,000','p',30,WHITE,False,typography_font_size_mobile=size(18)),text('3 beds · 3 baths<br>Approx. 3,112 sq ft','#F3F0EB',12)],width=size(54,'%'),width_mobile=size(52,'%'),padding=edges(22),padding_mobile=edges(16),flex_gap=gap(12),background_color='#17212D99',css_classes='eola-glass-card',border_border='solid',border_width=edges(1),border_color='#FFFFFF44'),
 card('Acreage',[heading('4.89','p',42,WHITE,False,typography_font_size_mobile=size(32),_css_classes='eola-stat-count'),label('ACRES',CHAMPAGNE),text('Space, privacy<br>and versatility.','#F3F0EB',12)],width=size(43,'%'),width_mobile=size(42,'%'),padding=edges(22),padding_mobile=edges(16),flex_gap=gap(12),background_color='#17212DBB',css_classes='eola-glass-card',margin=edges(18,0,0,0),margin_mobile=edges(0),border_border='solid',border_width=edges(1),border_color='#FFFFFF44')
 ],width=size(49,'%'),width_mobile=size(100,'%'),flex_align_items='flex-start',flex_direction_mobile='row',flex_gap=gap(12))
],flex_align_items='stretch',padding=edges(15,0,10,0))
scenes=row('Hero photo selector',[button(copy,'#main-content',True,True,text_padding=edges(10,18,10,18),border_color='#FFFFFF55',_css_classes='eola-scene-button eola-scene-'+key) for copy,key in [('01 / Exterior','exterior'),('02 / The home','interior'),('03 / The land','land')]],flex_direction_mobile='row',flex_wrap='wrap',flex_justify_content='center',flex_gap=gap(8),padding=edges(14,0,0,0))
hero=section('Hero - immersive property photograph',[nav,hero_intro,hero_word,hero_bottom,scenes],anchor='main-content',**background('hero','#101B2888'),padding=edges(30,40,38,40),padding_tablet=edges(24,28,30,28),padding_mobile=edges(24),border_radius=edges(30),margin=edges(18,18,0,18),margin_mobile=edges(10),boxed_width=size(1260),min_height=size(820),flex_gap=gap(16),min_height_mobile=size(0),flex_justify_content='space-between',css_classes='eola-immersive-hero')
summary=section('Property essentials',[
 row('Essential numbers',[
 col('Bedrooms',[heading('3','p',36,serif=False,_css_classes='eola-stat-count'),label('BEDROOMS',MUTED)],20),
 col('Bathrooms',[heading('3','p',36,serif=False,_css_classes='eola-stat-count'),label('BATHROOMS',MUTED)],20),
 col('Living space',[heading('3,112','p',36,serif=False,_css_classes='eola-stat-count'),label('APPROX. SQ FT',MUTED)],25),
 col('Property introduction',[text('A distinctive residence.<br>A different way to live.',INK,17)],30,flex_justify_content='center')
 ],flex_direction_mobile='row',flex_wrap_mobile='wrap',flex_align_items='center',css_classes='eola-facts-strip')
],padding=edges(36,60,36,60),padding_mobile=edges(25,22,25,22))
for c in summary['elements'][0]['elements']:c['settings'].update(width_mobile=size(46,'%'),flex_gap=gap(10))

feature_data=[('landscape','4.89 Acres','Space, privacy and versatility.','#land'),('hero','Custom Home','Thoughtfully designed living spaces with character and presence.','#home'),('barn','Barn + Workshop','Room for hobbies, projects, storage or lifestyle needs.','#workshop'),('terrace','Outdoor Living','Deck, pergola, grounds and room to enjoy the setting.','#outdoors')]
features=[]
for key,title,copy,link in feature_data:
 features.append(col(title,[photo(key,title,220,height_mobile=size(220),link_to='custom',link={'url':link}),heading(title,'h3',27),text(copy,n=13),button('Explore ↗',link,outline=True,text_padding=edges(9,17,9,17))],23.4,css_classes='eola-lift-card',flex_gap=gap(15),width_tablet=size(48,'%')))
highlights=section('01 - Why this property stands out',[
 *center([label('HOME. LAND. LIFESTYLE.'),heading('More than a<br>beautiful home.',n=60)]),
 text('Set on nearly five acres, this property brings together a distinctive residence, usable land, outdoor living, workshop/studio space, barn amenities and a peaceful setting.',align='center',_element_width='initial',_element_custom_width=size(740),_element_custom_width_mobile=size(100,'%'),_align_self='center'),
 row('Four feature cards',features,padding=edges(22,0,0,0),flex_wrap='wrap',flex_gap=gap(20))
],'property')

home=section('02 - The home',[
 row('Editorial home composition',[
 col('Living room photograph',[photo('living','Main living room',620,height_mobile=size(380),_css_classes='eola-scene-home')],47,css_classes='eola-image-reveal'),
 col('Home story and details',[
 label('01 / THE HOME'),heading('Designed to<br>live beautifully.',n=58),text('A home with architectural character, generous living spaces and a setting that feels removed from the everyday while remaining connected to Oregon wine-country living.'),button('Explore the full photo gallery ↗','#gallery'),
 row('Kitchen and primary suite',[col('Kitchen',[photo('kitchen','Kitchen',200,height_mobile=size(170)),label('KITCHEN',MUTED)],48),col('Primary suite',[photo('bedroom','Primary suite',200,height_mobile=size(170)),label('PRIMARY SUITE',MUTED)],48)],flex_direction_mobile='row',padding=edges(20,0,0,0))
 ],47,flex_justify_content='center')
 ]),
 row('Architectural detail',[col('Detail image',[photo('detail','Architectural interior detail',220,height_mobile=size(200))],35),col('Architectural detail caption',[heading('Character in<br>every corner.','h3',45),text('Architectural character. Generous living spaces. Oregon wine-country living.')],57,flex_justify_content='center')],padding=edges(10,0,0,0))
],'home')

land=section('03 - The land',[
 container('Land - immersive landscape',[
 button('4.89 acres · Oregon wine country','#details',True,_css_classes='eola-land-badge'),
 row('Land story',[col('Land breathing space',[],37,hide_mobile='hidden-mobile'),card('Nearly five acres',[
 label('02 / THE LAND',CHAMPAGNE),heading('Nearly five acres<br>of possibility.',n=52,color=WHITE),text('Open space, mature landscaping, natural rock features and usable acreage create opportunities for recreation, gardening, animals, hobbies or simply more room to breathe.','#E6E8EB'),text('4.89 acres · Barn · Workshop/studio<br>RV capability · Mature grounds',CHAMPAGNE,12)
 ],width=size(55,'%'),width_mobile=size(100,'%'),background_color='#17212DE6',css_classes='eola-glass-card')])
 ],**background('landscape','#17212D11'),min_height=size(600),min_height_mobile=size(600),padding=edges(36),padding_mobile=edges(20),border_radius=edges(28),flex_justify_content='space-between',css_classes='eola-land-scene')
],'land',padding=edges(25,30,50,30),padding_mobile=edges(16,14,30,14))

work=section('04 - Barn and workshop',[
 row('Barn and workshop introduction',[col('Heading',[label('03 / ROOM FOR YOUR PASSIONS'),heading('Space for what<br>matters to you.')],55),col('Introductory note',[text('Barn + workshop.<br>Room for hobbies, projects, storage or lifestyle needs.')],30,flex_justify_content='flex-end')]),
 row('Barn and workshop cards',[
 col('Barn',[photo('barn','Barn',380),row('Barn title',[heading('The barn','h3',35),label('01')]),text('Flexible space for storage, equipment, animals or property use.')],47,css_classes='eola-lift-card'),
 col('Workshop / Studio',[photo('workshop','Workshop / studio',380),row('Workshop title',[heading('Workshop / studio','h3',35),label('02')]),text('A dedicated space for woodworking, creative work, projects or serious hobbies.')],47,margin=edges(65,0,0,0),margin_mobile=edges(0),css_classes='eola-lift-card')
 ],padding=edges(20,0,0,0))
],'workshop')

outdoor=section('05 - Outdoor lifestyle',[
 row('Outdoor lifestyle composition',[
 col('Outdoor photograph',[photo('terrace','Deck and pergola',580,height_mobile=size(370),_css_classes='eola-scene-outdoors')],43),
 col('Outdoor story',[label('04 / LIFE OUTSIDE'),heading('Step outside.<br>Stay awhile.',n=68),text('The outdoor spaces create a natural extension of the home, with room to relax, entertain and take in the peaceful surroundings.'),card('Outdoor amenities',[label('MAKE ROOM FOR THE EVERYDAY',INK),heading('Deck. Pergola.<br>Your own pace.','h3',33),text('Deck · Pergola · Mature Trees<br>Private Setting · Scenic Surroundings',INK,13)],background_color=CHAMPAGNE),button('See the grounds ↗','#gallery',outline=True)],50,flex_justify_content='center')
 ])
],'outdoors')
video=section('06 - Property experience',[
 row('Experience heading',[col('Property experience title',[label('TAKE A CLOSER LOOK'),heading('Experience<br>the property.')],60),col('Tour invitation',[text('See why this property feels different in person.'),button('Schedule your private tour ↗',tour)],34,flex_justify_content='flex-end')]),
 widget('image-carousel','Interactive property preview',carousel=[media(key) for key in ['hero','living','kitchen','landscape','terrace']],image_size='full',slides_to_show='1',slides_to_scroll='1',navigation='both',autoplay='no',pause_on_hover='yes',pause_on_interaction='yes',infinite='yes',effect='slide',speed=800,show_caption='none',arrows_position='inside',arrows_color=WHITE,arrows_size=size(28),dots_position='outside',dots_color=GOLD,image_spacing='custom',image_spacing_custom=size(0),eola_image_height=size(560),eola_image_height_mobile=size(320),image_border_radius=edges(28),_css_classes='eola-property-carousel'),
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
filters=row('Gallery filters',[button(title,'#gallery',outline=True,text_padding=edges(12,22,12,22),border_color='#D5D5D5',_css_classes='eola-filter eola-filter-'+key) for title,key in [('All views','all'),('The home','home'),('Outdoors','grounds'),('Barn & studio','spaces')]],flex_wrap='wrap',flex_direction_mobile='row',flex_gap=gap(10),flex_justify_content='center')
gallery=section('07 - Photo gallery',[
 *center([label('EVERY ANGLE, EVERY POSSIBILITY'),heading('Explore the property.'),text('A curated look at the home and grounds. Select a view to take a closer look.')]),filters,
 row('Interactive photo gallery',tiles,flex_wrap='wrap',flex_gap=gap(26),flex_justify_content='flex-start',css_classes='eola-gallery-grid',padding=edges(22,0,0,0))
],'gallery')

facts_cards=[]
for k,v in [('Offered at','$1,499,000'),('Bedrooms','3'),('Bathrooms','3'),('Living area','Approx. 3,112 sq ft'),('Lot size','4.89 acres'),('Address','9055 SE Eola Hills Rd,<br>Amity, OR')]:
 facts_cards.append(card(k,[label(k.upper(),MUTED),heading(v,'p',24,serif=False)],width=size(48,'%'),width_mobile=size(100,'%'),padding=edges(24),flex_gap=gap(14)))
facts=section('08 - Property details',[
 row('At a glance',[col('Property details heading',[label('THE ESSENTIALS'),heading('Property<br>at a glance.',n=60),text('9055 SE Eola Hills Rd<br>Amity, Oregon'),button('Ask about the property ↗',tour,outline=True)],34,flex_justify_content='center'),col('Facts',[row('Property details cards',facts_cards,flex_wrap='wrap',flex_gap=gap(18))],61)])
],'details')
team=section('09 - Listing team',[
 container('Listing team - inset navy panel',[
 row('Meet the team',[
 col('Team introduction',[label('MEET YOUR LISTING TEAM',CHAMPAGNE),heading('Private tours &<br>property<br>information.',n=50,color=WHITE),text('Wine Country Properties + eXp.','#D5DCE3',13)],31,flex_justify_content='center'),
 row('Agents',[
 card('Linda Lindsay',[heading('LL','p',48,CHAMPAGNE),heading('Linda Lindsay','h3',34,WHITE),text('Broker & Realtor','#D5DCE3',13),button('(503) 720-1805','tel:+15037201805',True,True,text_padding=edges(13,16,13,16)),button('Email Linda ↗','mailto:linda.lindsay@exprealty.com',True,True,text_padding=edges(13,16,13,16))],width=size(48,'%'),width_mobile=size(100,'%'),background_color='#FFFFFF09',border_border='solid',border_width=edges(1),border_color='#FFFFFF22',padding=edges(28),flex_gap=gap(16)),
 card('Anita Borgeas',[heading('AB','p',48,CHAMPAGNE),heading('Anita Borgeas','h3',34,WHITE),text('Principal Broker','#D5DCE3',13),text('Private tours and<br>property information.','#D5DCE3',13),button('Contact the team ↗',tour,True,True,text_padding=edges(13,16,13,16))],width=size(48,'%'),width_mobile=size(100,'%'),background_color='#FFFFFF09',border_border='solid',border_width=edges(1),border_color='#FFFFFF22',padding=edges(28),flex_gap=gap(16))
 ],width=size(65,'%'),width_mobile=size(100,'%'),flex_gap=gap(20))
 ])
 ],background_background='classic',background_color=INK,border_radius=edges(28),padding=edges(48),padding_tablet=edges(28),padding_mobile=edges(24))
],'team',padding=edges(42,24,42,24),padding_mobile=edges(24,12,24,12))
closing=section('10 - Your next chapter',[
 container('Closing invitation',[
 *center([label('9055 SE EOLA HILLS RD / AMITY, OREGON',CHAMPAGNE),heading('Could this be the property<br>you’ve been waiting for?',n=62,color=WHITE),text('Some homes offer space. Others offer lifestyle. This one offers both.','#F3F0EB')]),
 row('Final actions',[button('Schedule a private showing ↗',tour,True),button('Contact the listing team','#team',True,True)],flex_justify_content='center',flex_gap=gap(14))
 ],**background('landscape','#101B28CF'),border_radius=edges(28),padding=edges(90,40,90,40),padding_mobile=edges(55,24,55,24),flex_gap=gap(28))
],padding=edges(20,24,40,24),padding_mobile=edges(12,12,32,12))
footer=section('Footer - editable branding',[
 row('Footer details',[col('Brand',[heading('Wine Country','p',26),label('PROPERTIES + eXp',MUTED),text('9055 SE Eola Hills Rd · Amity, Oregon',n=12)],50),col('Footer details',[text('Linda Lindsay · Anita Borgeas<br>Equal Housing Opportunity',n=12),button('Back to top ↑','#top',outline=True,text_padding=edges(12,22,12,22))],34)]),
 text('Property details are supplied by the listing team and are subject to confirmation.',n=11)
],html_tag='footer',padding=edges(22,48,45,48),padding_mobile=edges(24))
skip=button('Skip to property','#main-content',_css_classes='eola-skip')
hero['settings'].update(margin=edges(0),margin_mobile=edges(0))
hero_word['settings']['typography_font_size_mobile']=size(16,'vw')
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
template={'title':'Eola Hills — Immersive Property Landing Page','type':'page','version':'0.4','page_settings':{'template':'elementor_canvas','hide_title':'yes','background_background':'classic','background_color':PAPER},'content':[skip,hero_frame,summary,highlights,home,land,work,outdoor,video,gallery,facts,team,closing,footer]}
(PLUGIN/'templates/property.json').write_text(json.dumps(template,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
print(f'Generated {counter} native Elementor elements.')
