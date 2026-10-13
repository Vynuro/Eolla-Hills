"""Generate a portable native Elementor template with replaceable stock photography."""
from pathlib import Path
import json
import hashlib

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / 'wordpress/wp-content/plugins/eola-hills'
ASSETS = PLUGIN / 'assets'
INK, GREEN, PAPER, SAND, MUTED, GOLD = '#192333', '#192333', '#F8F5EF', '#EEE8DE', '#656570', '#997044'
counter = 0

def ident():
    global counter
    counter += 1
    return hashlib.sha1(f'eola-{counter}'.encode()).hexdigest()[:8]

def size(n, unit='px'): return {'unit': unit, 'size': n, 'sizes': []}
def edges(t=0, r=None, b=None, l=None):
    return {'unit': 'px', 'top': str(t), 'right': str(t if r is None else r), 'bottom': str(t if b is None else b), 'left': str(t if l is None else l), 'isLinked': False}
def typo(n, serif=False, weight='400', line=1.2):
    return {'typography_typography': 'custom', 'typography_font_family': 'Eola Display' if serif else 'Eola Sans', 'typography_font_size': size(n), 'typography_font_weight': weight, 'typography_line_height': size(line, 'em')}
def widget(kind, name, **settings):
    return {'id': ident(), 'elType': 'widget', 'widgetType': kind, 'settings': {'_title': name, **settings}, 'elements': []}
def heading(text, tag='h2', n=46, color=INK, **settings):
    return widget('heading', text.replace('<br>', ' '), title=text, header_size=tag, title_color=color, **{**typo(n, tag not in ['p','span']), 'typography_font_size_mobile': size(min(n,34)), **settings})
def label(text, color=GOLD):
    return heading(text, 'p', 11, color, **{'typography_letter_spacing':size(2), 'typography_font_weight':'600'})
def text(copy, color=MUTED, n=16, **settings):
    return widget('text-editor', copy[:50], editor=f'<p>{copy}</p>', text_color=color, **{**typo(n, line=1.8), **settings})
def button(copy, url, light=False, outline=False, **settings):
    color = PAPER if light else GREEN
    return widget('button', copy, text=copy, link={'url':url}, align='left', button_text_color=color if outline else (GREEN if light else PAPER), background_color='#00000000' if outline else color, button_background_hover_color=SAND if light else '#38485E', hover_color=GREEN if light else PAPER, border_border='solid', border_width=edges(1), border_color=color, border_radius=edges(0), text_padding=edges(17,24,17,24), **{**typo(13,weight='500'), **settings})
def container(name, children, row=False, bg=None, **settings):
    s={'_title': name, 'content_width':'full', 'flex_direction':'row' if row else 'column', 'flex_direction_mobile':'column', 'flex_gap':{'unit':'px','size':20,'column':'20','row':'20','isLinked':True}, 'padding':edges(0), 'flex_align_items':'stretch'}
    if bg: s.update(background_background='classic',background_color=bg)
    s.update(settings)
    return {'id':ident(),'elType':'container','isInner':False,'settings':s,'elements':children}
def section(name, children, bg=PAPER, anchor=None, **settings):
    return container(name,children,bg=bg,**{'html_tag':'section','content_width':'boxed','boxed_width':size(1200),'padding':edges(96,40,96,40),'padding_tablet':edges(64,32,64,32),'padding_mobile':edges(56,22,56,22), **({'_element_id':anchor} if anchor else {}), **settings})
def row(name,children,**settings):
    return container(name,children,True,**{'flex_justify_content':'space-between', **settings})
def col(name, children, width=48, **settings):
    return container(name,children,width=size(width,'%'),width_mobile=size(100,'%'),**settings)
def image(name, shape='landscape', **settings):
    title=name.split(' — ')[0].split(' —')[0]
    key='hero'
    for needle,photo in [('living','living'),('kitchen','kitchen'),('suite','bedroom'),('architectural','detail'),('grounds','landscape'),('acreage','landscape'),('aerial','landscape'),('barn','barn'),('workshop','workshop'),('deck','terrace'),('pergola','terrace')]:
        if needle in title.lower(): key=photo; break
    if name.startswith('Professional exterior'): title='A home with room to breathe';key='hero'
    return widget('image',title,**{'image':{'url':f'{{{{EOLA_ASSETS}}}}/photos/{key}.webp','id':0},'image_size':'full','width':size(100,'%'),'height':size(400),'height_mobile':size(280),'object-fit':'cover','caption_source':'custom','caption':title,'image_border_radius':edges(0),'caption_text_color':MUTED,'caption_typography_typography':'custom','caption_typography_font_family':'Eola Sans','caption_typography_font_size':size(12), **settings})
def image_panel(name, shape='landscape', **settings):
    return container(name,[image(name,shape)],**settings)

tour='mailto:linda.lindsay@exprealty.com?subject=Private%20tour%20-%209055%20SE%20Eola%20Hills%20Rd'

header=section('Header - brand and navigation',[
    row('Header row',[
        col('Editable wordmark',[heading('WINE COUNTRY','p',16,typography_letter_spacing=size(3)),heading('P R O P E R T I E S   /   e X p','p',9,MUTED)],36,flex_gap={'column':'6','row':'6','unit':'px'}),
        row('Page navigation',[button('The property','#property',outline=True),button('Gallery','#gallery',outline=True),button('Private tour',tour)],width=size(62,'%'),width_mobile=size(100,'%'),flex_wrap='wrap',flex_direction_mobile='row',flex_gap={'column':'10','row':'10','unit':'px'},flex_justify_content='flex-end',flex_justify_content_mobile='flex-start')
    ],flex_align_items='center')
])
header['settings'].update(html_tag='header',padding=edges(26,40,26,40),padding_mobile=edges(22))

hero=section('Hero - property introduction',[
    row('Hero composition',[
        col('Hero copy',[
            label('AMITY, OREGON  /  4.89 ACRES'),
            heading('Unmistakably<br>special.', 'h1', 80, typography_font_size_tablet=size(60),typography_font_size_mobile=size(52)),
            text('A rare combination of home, land,<br>lifestyle & possibility.',n=19),
            heading('9055 SE Eola Hills Rd','p',14,typography_font_weight='600'),
            row('Hero actions',[button('Schedule a private tour',tour),button('Property highlights ↓','#property',outline=True)],flex_wrap='wrap',flex_direction_mobile='column',flex_gap={'column':'12','row':'12','unit':'px'},flex_justify_content='flex-start'),
        ],49,flex_justify_content='center',padding=edges(35,40,35,0),padding_mobile=edges(0,0,32,0)),
        col('Hero photography',[image('Professional exterior / aerial photography coming soon','hero',height=size(650),height_mobile=size(420),caption_source='none')],51),
    ],flex_gap={'column':'0','row':'0','unit':'px'}),
    row('Property facts',[
        col('Price',[label('OFFERED AT'),heading('$1,499,000','p',28)],29),
        col('Bedrooms',[heading('3','p',30),label('BEDROOMS')],15),
        col('Bathrooms',[heading('3','p',30),label('BATHROOMS')],15),
        col('Living area',[heading('3,112','p',30),label('APPROX. SQ FT')],19),
        col('Acreage',[heading('4.89','p',30),label('ACRES')],18),
    ],padding=edges(36,0,0,0),border_border='solid',border_width=edges(1,0,0,0),border_color='#D9D3CA',flex_wrap_mobile='wrap',flex_direction_mobile='row')
],anchor='main-content')
hero['settings'].update(padding=edges(36,40,52,40),padding_mobile=edges(24,22,40,22))
for c in hero['elements'][1]['elements']:
    c['settings']['width_mobile']=size(46,'%')
    if c['settings']['_title'] != 'Price':
        c['elements'][0]['settings']['_css_classes']='eola-stat-count'

features=[]
for num,title,copy in [('01','Room to breathe','4.89 acres of space, privacy and versatility.'),('02','A home with character','Thoughtfully designed living spaces with presence.'),('03','Space to create','Barn and workshop space for projects and possibilities.'),('04','Life outdoors','A deck, pergola and grounds to make your own.')]:
    features.append(col(title,[label(num,'#D1B694'),heading(title,'h3',25,PAPER),text(copy,'#DCE0E6',14)],23,padding=edges(20,0,0,0),border_border='solid',border_width=edges(1,0,0,0),border_color='#59616C'))
highlights=section('01 - Why this property stands out',[
    label('A DIFFERENT WAY TO LIVE','#D1B694'),
    row('Highlights introduction',[heading('More than a<br>beautiful home.',n=52,color=PAPER),col('Introduction',[text('Set on nearly five acres, this property brings together a distinctive residence, usable land, outdoor living and a peaceful setting.','#DCE0E6')],45)]),
    row('Four property highlights',features,padding=edges(28,0,0,0))
],GREEN,'property')

home=section('02 - The home',[
    row('Home introduction',[col('Interior feature image',[image('Main living room — photography coming soon','interior')],56),col('Home story',[label('01 / THE HOME'),heading('Designed to live<br>beautifully.'),text('Architectural character. Generous living spaces. A setting that feels removed from the everyday, while remaining connected to Oregon wine-country living.'),button('Explore the gallery ↗','#gallery',outline=True)],38,flex_justify_content='center')]),
    row('Interior details',[col('Kitchen image',[image('Kitchen — photography coming soon','interior')],32),col('Primary suite image',[image('Primary suite — photography coming soon','interior')],32),col('Architectural detail',[image('Architectural detail — photography coming soon','detail')],32)],padding=edges(20,0,0,0))
],anchor='home')
land=section('03 - The land',[
    row('Land story',[col('Land heading',[label('02 / THE LAND'),heading('Nearly five acres<br>of possibility.')],47),col('Land description',[text('Open space, mature landscaping, natural rock features and usable acreage. Room for recreation, gardening, hobbies — or simply more room to breathe.'),text('4.89 ACRES   ·   BARN   ·   WORKSHOP / STUDIO',n=12)],43,flex_justify_content='center')]),
    image('The grounds & acreage','wide',height=size(520)),
],SAND,anchor='land')
work=section('04 - Barn and workshop',[
    label('03 / ROOM FOR YOUR PASSIONS'),heading('Space for what matters to you.'),
    row('Barn and workshop stories',[
        col('Barn',[image('Barn — photography coming soon','barn'),heading('The barn','h3',32),text('Flexible space for storage, equipment and property use. Final amenities to be confirmed.')],49),
        col('Workshop',[image('Workshop / studio — photography coming soon','detail'),heading('The workshop / studio','h3',32),text('A dedicated space for woodworking, creative work, projects or serious hobbies.')],49)
    ])
])
outdoor=section('05 - Outdoor lifestyle',[
    row('Outdoor feature',[col('Outdoor story',[label('04 / LIFE OUTSIDE','#D1B694'),heading('Step outside.<br>Stay awhile.',n=56,color=PAPER),text('The outdoor spaces create a natural extension of the home, with room to relax, entertain and take in the peaceful surroundings.','#DCE0E6'),text('DECK  /  PERGOLA  /  MATURE TREES','#D1B694',12)],40,flex_justify_content='center'),col('Outdoor photography',[image('Deck & pergola — photography coming soon','outdoor',caption_text_color='#DCE0E6')],54)])
],GREEN)
video=section('06 - Property preview',[
    label('TAKE A CLOSER LOOK'),heading('Experience the property.'),
    widget('image-carousel','Interactive property preview',carousel=[{'url':f'{{{{EOLA_ASSETS}}}}/photos/{photo}.webp','id':0} for photo in ['hero','living','kitchen','terrace']],image_size='full',slides_to_show='1',slides_to_scroll='1',navigation='both',autoplay='no',pause_on_hover='yes',pause_on_interaction='yes',infinite='yes',effect='slide',speed=600,show_caption='none',arrows_position='inside',arrows_color=PAPER,dots_position='outside',dots_color=GREEN,image_spacing='custom',image_spacing_custom=size(0),eola_image_height=size(560),eola_image_height_mobile=size(320),_css_classes='eola-property-carousel'),
    row('Video next step',[text('See why this property feels different in person.'),button('Schedule your private tour',tour,outline=True)],flex_align_items='center')
],anchor='film')
gallery_tiles=[]
labels=['Exterior','Kitchen','Living room','Primary suite','Deck','Grounds','Barn','Workshop','Acreage','Aerial']
shapes=['hero','interior','interior','interior','outdoor','landscape','barn','detail','landscape','wide']
categories=['home','home','home','home','grounds','grounds','spaces','spaces','grounds','grounds']
for title,shape,category in zip(labels,shapes,categories):
    gallery_tiles.append(col(title,[image(title+' — photography coming soon',shape,link_to='file',open_lightbox='yes')],49,css_classes='eola-gallery-tile eola-category-'+category))
filters=row('Gallery filters',[button(title,'#gallery',outline=True,_css_classes='eola-filter eola-filter-'+key) for title,key in [('All views','all'),('The home','home'),('Outdoors','grounds'),('Barn & studio','spaces')]],flex_justify_content='flex-start',flex_wrap='wrap',flex_direction_mobile='row',flex_gap={'unit':'px','row':'12','column':'12'})
gallery=section('07 - Photo gallery',[row('Gallery heading',[col('Gallery title',[label('EVERY ANGLE, EVERY POSSIBILITY'),heading('Explore the property.')],65),col('Gallery note',[text('A curated look at the home and grounds. Select a view to take a closer look.',n=14)],30)]),filters,row('Interactive photo gallery',gallery_tiles,flex_wrap='wrap',css_classes='eola-gallery-grid')],anchor='gallery')
gallery['settings'].update(background_color='#F0EBE3')
details=[]
for k,v in [('Address','9055 SE Eola Hills Rd, Amity, OR'),('Offered at','$1,499,000'),('Bedrooms / bathrooms','3 bedrooms / 3 bathrooms'),('Living area','Approx. 3,112 sq ft'),('Lot size','4.89 acres')]:
    details.append(row(k,[text(k,n=14),heading(v,'p',17)],padding=edges(20,0,20,0),border_border='solid',border_width=edges(0,0,1,0),border_color='#D9D3CA'))
facts=section('08 - Property details',[row('Details layout',[col('Details heading',[label('THE ESSENTIALS'),heading('Property<br>at a glance.'),text('A little more room.<br>A lot more possibility.')],36),col('Property details',details,58)])],anchor='details')
team=section('09 - Listing team',[
    label('YOUR NEXT CHAPTER STARTS HERE'),heading('Private tours &<br>property information.'),
    row('Agents',[
        col('Linda Lindsay',[label('LL'),heading('Linda Lindsay','h3',38),text('Broker & Realtor',n=14),button('(503) 720-1805','tel:+15037201805',outline=True),button('Email Linda ↗','mailto:linda.lindsay@exprealty.com',outline=True)],48,bg=PAPER,padding=edges(36)),
        col('Anita Borgeas',[label('AB'),heading('Anita Borgeas','h3',38),text('Principal Broker',n=14),text('For private tours and property information, contact the listing team through Linda.'),button('Contact the listing team ↗',tour,outline=True)],48,bg=PAPER,padding=edges(36))
    ],padding=edges(24,0,0,0))
],SAND,'team')
closing=section('10 - Final invitation',[label('9055 SE EOLA HILLS RD  /  AMITY, OREGON','#D1B694'),heading('Could this be the place<br>you’ve been waiting for?',n=58,color=PAPER),text('Some homes offer space. Others offer lifestyle. This one offers both.','#DCE0E6'),row('Final actions',[button('Schedule a private showing',tour,light=True),button('Contact the listing team','#team',light=True,outline=True)],flex_justify_content='center',flex_gap={'unit':'px','row':'14','column':'14'})],GREEN)
for child in closing['elements'][:3]: child['settings']['align']='center'
closing['settings']['flex_align_items']='center'
footer=section('Footer - editable branding and information',[
    row('Footer brand',[col('Footer wordmark',[heading('WINE COUNTRY PROPERTIES','p',13,typography_letter_spacing=size(2)),text('eXp Realty  /  Oregon',n=12)],48),col('Footer contact',[text('9055 SE Eola Hills Rd<br>Amity, Oregon',n=13),text('Equal Housing Opportunity',n=12)],38)]),
    text('Property details are supplied by the listing team and are subject to confirmation.',n=11),
    button('Back to top ↑','#top',outline=True)
])
footer['settings'].update(html_tag='footer',padding=edges(44,40,44,40),padding_mobile=edges(32,22,32,22))
header['settings']['_element_id']='top'
header['settings']['css_classes']='eola-header'
for nav in header['elements'][0]['elements'][1]['elements'][:2]:
    nav['settings'].update(border_width=edges(0),text_padding=edges(17,12,17,12),_css_classes='eola-nav-link')
hero['elements'][0]['elements'][1]['settings']['css_classes']='eola-hero-media'
hero['elements'][0]['elements'][0]['settings']['css_classes']='eola-hero-copy'
for item in [highlights,home,land,work,outdoor,video,gallery,facts,team,closing]:
    item['settings']['css_classes']='eola-reveal-section'
skip=button('Skip to property','#main-content',_css_classes='eola-skip')
template={'title':'Eola Hills — Property Landing Page','type':'page','version':'0.4','page_settings':{'template':'elementor_canvas','hide_title':'yes','background_background':'classic','background_color':PAPER},'content':[skip,header,hero,highlights,home,land,work,outdoor,video,gallery,facts,team,closing,footer]}
(PLUGIN/'templates').mkdir(parents=True,exist_ok=True)
(PLUGIN/'templates/property.json').write_text(json.dumps(template,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')

print(f'Generated {counter} native Elementor elements with editable stock photography.')
