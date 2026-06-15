# -*- coding: utf-8 -*-
"""kiduri 概念図をPNG生成(きづりサイト寄りの温かい配色・BIZ UDGothic)。"""
import os
from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
FIG = os.path.join(HERE, "figures"); os.makedirs(FIG, exist_ok=True)

FONT_CANDIDATES = ["C:/Windows/Fonts/BIZ-UDGothicR.ttc","C:/Windows/Fonts/YuGothM.ttc","C:/Windows/Fonts/meiryo.ttc"]
BOLD_CANDIDATES = ["C:/Windows/Fonts/BIZ-UDGothicB.ttc","C:/Windows/Fonts/YuGothB.ttc","C:/Windows/Fonts/meiryob.ttc"]

def font(size, bold=False):
    for p in (BOLD_CANDIDATES if bold else FONT_CANDIDATES):
        if os.path.exists(p):
            try: return ImageFont.truetype(p, size)
            except Exception: pass
    return ImageFont.load_default()

# きづり ブランド配色
ORANGE=(232,131,58); TEAL=(42,143,143); INK=(44,62,80); NAVY=(26,39,68)
CREAM=(247,245,240); BEIGE=(239,234,224); GRAY=(107,123,141); WHITE=(255,255,255)
LORANGE=(252,232,214); LTEAL=(216,237,237); STROKE=(214,205,191)
BG=(255,255,255)

def W(draw,text,f):
    b=draw.textbbox((0,0),text,font=f); return b[2]-b[0], b[3]-b[1]

def center_text(draw, box, lines, f, color=INK, lh=1.34):
    x0,y0,x1,y1=box; cx=(x0+x1)/2; cy=(y0+y1)/2
    sizes=[W(draw,l,f) for l in lines]; th=sum(s[1]*lh for s in sizes); y=cy-th/2
    for (lw,lhh),l in zip(sizes,lines):
        draw.text((cx-lw/2,y),l,font=f,fill=color); y+=lhh*lh

def box(draw, x,y,w,h, lines, f, fill=BEIGE, outline=ORANGE, color=INK, r=18):
    draw.rounded_rectangle([x,y,x+w,y+h], radius=r, fill=fill, outline=outline, width=3)
    center_text(draw,(x,y,x+w,y+h),lines,f,color)

def arrow(draw, x0,y0,x1,y1, color=GRAY, wdt=5, head=13):
    import math
    draw.line([x0,y0,x1,y1], fill=color, width=wdt)
    ang=math.atan2(y1-y0,x1-x0)
    for s in (math.pi-0.5, math.pi+0.5):
        draw.line([x1,y1, x1+head*math.cos(ang+s), y1+head*math.sin(ang+s)], fill=color, width=wdt)

def badge(d, x, y, text, f, fill, fg, padx=12, pady=7):
    tw,th=W(d,text,f)
    d.rounded_rectangle([x,y,x+tw+padx*2,y+th+pady*2], radius=13, fill=fill)
    d.text((x+padx,y+pady), text, font=f, fill=fg)
    return x+tw+padx*2

# ---------- 図A: 知識循環ループ ----------
def fig_loop():
    im=Image.new("RGB",(1640,560),BG); d=ImageDraw.Draw(im)
    ft=font(30,True); fs=font(22); ftitle=font(28,True)
    d.text((44,28),"知識循環ループ ― 毎日の記録が、次の支援を賢くする",font=ftitle,fill=INK)
    steps=[("毎日の\n連絡帳",BEIGE,TEAL),("AIが\n下書き",LORANGE,ORANGE),("職員が\n修正",LTEAL,TEAL),
           ("支援と\n成果",BEIGE,ORANGE),("知識として\n蓄積",LORANGE,ORANGE)]
    bw,bh=252,120; gap=(1640-44*2-bw*5)/4; y=120; cxs=[]
    for i,(t,fill,oc) in enumerate(steps):
        x=44+i*(bw+gap); box(d,x,y,bw,bh,t.split("\n"),ft,fill=fill,outline=oc); cxs.append(x)
        if i<len(steps)-1: arrow(d,x+bw+6,y+bh/2,x+bw+gap-6,y+bh/2)
    fy=y+bh+72
    d.line([cxs[4]+bw/2, y+bh, cxs[4]+bw/2, fy], fill=ORANGE, width=5)
    d.line([cxs[4]+bw/2, fy, cxs[1]+bw/2, fy], fill=ORANGE, width=5)
    arrow(d, cxs[1]+bw/2, fy, cxs[1]+bw/2, y+bh+4, color=ORANGE)
    center_text(d,(cxs[1]+bw,y+bh+14,cxs[4],fy-12),["次回の下書きへ反映(=使うほど修正が減る)"],fs,color=ORANGE)
    p=os.path.join(FIG,"diagram_loop.png"); im.save(p); return p

# ---------- 図B: L1→L4 蒸留 ----------
def fig_distill():
    im=Image.new("RGB",(1640,620),BG); d=ImageDraw.Draw(im)
    ft=font(28,True); fe=font(20); ftitle=font(28,True)
    d.text((44,28),"記録の蒸留 ― 毎日の記述から「支援知」へ",font=ftitle,fill=INK)
    rows=[("L4 原理",INK,WHITE,"複数ケースから抽出した普遍原理(見通し形成/自己決定/安心基地/役割形成)"),
          ("L3 支援知",TEAL,WHITE,"個別を超えた知見(対象=中学生・ASD傾向/有効支援=見通し提示・役割形成)"),
          ("L2 構造化",(120,190,190),INK,"事実 / 支援 / 結果 / 仮説 / タグ を抽出(本文は残さず=個人情報なし)"),
          ("L1 生データ",BEIGE,INK,"毎日の記述そのまま「今日は落ち着いて活動に参加できた」")]
    lx=96; lw=348; ex0=474; exr=1600; bh=98; gap=30; y=98
    n=len(rows)
    # 左端に L1→L4 の上向き矢印(蒸留の方向)。箱のテキストには重ねない。
    bottom=y+(n-1)*(bh+gap)+bh; top=y
    arrow(d, 56, bottom, 56, top, color=ORANGE, wdt=7, head=16)
    for i,(label,fill,tc,ex) in enumerate(rows):
        yy=y+i*(bh+gap)
        d.rounded_rectangle([lx,yy,lx+lw,yy+bh],radius=16,fill=fill,outline=INK,width=2)
        center_text(d,(lx,yy,lx+lw,yy+bh),[label],ft,color=tc)
        d.rounded_rectangle([ex0,yy,exr,yy+bh],radius=16,fill=CREAM,outline=STROKE,width=2)
        center_text(d,(ex0+22,yy,exr-22,yy+bh),wrap(ex,46),fe,color=INK)
    p=os.path.join(FIG,"diagram_distill.png"); im.save(p); return p

# ---------- 図C: 明示×暗黙の二輪 ----------
def fig_twowheel():
    im=Image.new("RGB",(1640,520),BG); d=ImageDraw.Draw(im)
    ft=font(25,True); fs=font(21); ftitle=font(28,True)
    d.text((44,28),"自己改善の二輪 ― 施設らしさを下書きに織り込む",font=ftitle,fill=INK)
    box(d,60,140,440,150,["【明示の基準】","管理者がAIと対話して","施設の記録基準を決める"],ft,fill=LORANGE,outline=ORANGE)
    box(d,60,330,440,150,["【暗黙の学習】","確定稿と修正理由から","施設の書き方を自動で学ぶ"],ft,fill=LTEAL,outline=TEAL)
    box(d,720,235,360,150,["AIの下書き","に反映"],font(30,True),fill=BEIGE,outline=INK)
    box(d,1240,235,340,150,["記録の質が上がる","職員が基準を体得"],ft,fill=CREAM,outline=ORANGE)
    arrow(d,500,215,715,300,color=ORANGE,wdt=6)
    arrow(d,500,405,715,320,color=TEAL,wdt=6)
    arrow(d,1080,310,1235,310,color=INK,wdt=6)
    center_text(d,(60,492,1580,516),["※ 明示の基準は施設の方針(個人情報なし)。暗黙の学習は施設の同意がある場合に作用。"],fs,color=GRAY)
    p=os.path.join(FIG,"diagram_twowheel.png"); im.save(p); return p

# ---------- 図(将来): 見本キュレーション(模擬データ) ----------
def fig_curation():
    im=Image.new("RGB",(1500,640),BG); d=ImageDraw.Draw(im)
    ftitle=font(26,True); fb=font(19); fbsm=font(17); fs=font(20); fnote=font(16)
    d.rounded_rectangle([18,18,1482,622], radius=18, outline=STROKE, width=2, fill=CREAM)
    d.text((44,40),"見本キュレーション(学習に使う記録の選別)",font=ftitle,fill=INK)
    d.text((44,86),"良い記録だけを学習に。見本にしたい記録は「見本採用」、不要は「学習除外」に。",font=fbsm,fill=GRAY)
    x=44; y=120
    x=badge(d,x,y,"見本採用 8",fb,LORANGE,ORANGE)+10
    x=badge(d,x,y,"学習除外 3",fb,BEIGE,(90,96,105))+10
    x=badge(d,x,y,"未判定 12",fb,BEIGE,(90,96,105))+10
    x=badge(d,x,y,"確定記録 23",fb,BEIGE,GRAY)+10
    d.rounded_rectangle([44,168,1456,214], radius=12, fill=LTEAL, outline=TEAL, width=2)
    d.text((62,182),"★ 因果まで書けた良質な未判定の記録が 5 件。見本採用すると、AIの下書きの質が上がります。",font=fb,fill=TEAL)
    for txt in ["朝の支度に手順表を用いると自分で進められた。見通しを示すと落ち着いて取り組めた。",
                "集団活動の前に役割を伝えると安心して参加できた。終了後に一緒に振り返りを行った。"]:
        cy=236 if txt.startswith("朝") else 414
        d.rounded_rectangle([44,cy,1456,cy+158], radius=14, outline=STROKE, width=2, fill=WHITE)
        bx=62; by=cy+16
        bx=badge(d,bx,by,"推奨",fbsm,LORANGE,ORANGE)+8
        bx=badge(d,bx,by,"個別支援計画",fbsm,BEIGE,(90,96,105))+8
        bx=badge(d,bx,by,"因果あり",fbsm,LTEAL,TEAL)+8
        bx=badge(d,bx,by,"修正量 30%",fbsm,BEIGE,GRAY)+8
        d.text((62,by+46), txt+" …", font=fs, fill=INK)
        d.rounded_rectangle([62,cy+104,196,cy+142], radius=10, fill=ORANGE); d.text((92,cy+114),"見本採用",font=fb,fill=WHITE)
        d.rounded_rectangle([210,cy+104,344,cy+142], radius=10, outline=ORANGE, width=2); d.text((232,cy+114),"学習除外",font=fb,fill=ORANGE)
    d.text((44,596),"※ 本図はイメージ(模擬データ)。プレビューはマスク済。",font=fnote,fill=GRAY)
    p=os.path.join(FIG,"fig3.png"); im.save(p); return p

# ---------- 図E: 別添「評価状況の全体像」(客観×主観レーダー + 成果3指標) ----------
def fig_betten():
    import math
    BLUE=(59,130,246); GREEN=(34,197,94)
    W0,H0=1640,640
    im=Image.new("RGB",(W0,H0),BG); d=ImageDraw.Draw(im)
    ft=font(28,True); flbl=font(18); fleg=font(19,True); fcap=font(18)
    # ---- レーダー(発達段階別評価=全児童 5領域)----
    labels=["健康・生活","運動・感覚","認知・行動","言語・コミュニケーション","人間関係・社会性"]
    obj=[7,5,8,4,6]; subj=[8,6,6,5,8]; n=5; R=176; cx,cy=410,318
    def ang(i): return -math.pi/2 + i*2*math.pi/n
    def pt(i,val): a=ang(i); rr=R*val/10.0; return (cx+rr*math.cos(a), cy+rr*math.sin(a))
    for ring in (0.5,0.75,1.0):
        d.polygon([(cx+R*ring*math.cos(ang(i)), cy+R*ring*math.sin(ang(i))) for i in range(n)], outline=STROKE)
    for i in range(n):
        d.line([cx,cy, cx+R*math.cos(ang(i)), cy+R*math.sin(ang(i))], fill=STROKE, width=1)
    ov=Image.new("RGBA",(W0,H0),(0,0,0,0)); od=ImageDraw.Draw(ov)
    od.polygon([pt(i,obj[i]) for i in range(n)], fill=BLUE+(60,), outline=BLUE+(255,))
    od.polygon([pt(i,subj[i]) for i in range(n)], fill=GREEN+(55,), outline=GREEN+(255,))
    im=Image.alpha_composite(im.convert("RGBA"),ov).convert("RGB"); d=ImageDraw.Draw(im)
    for i in range(n):
        for val,c in ((obj[i],BLUE),(subj[i],GREEN)):
            x,y=pt(i,val); d.ellipse([x-4,y-4,x+4,y+4],fill=c)
    for i,lab in enumerate(labels):
        a=ang(i); lx=cx+(R+30)*math.cos(a); ly=cy+(R+24)*math.sin(a)
        lines=wrap(lab,7) if len(lab)>8 else [lab]; yy=ly-(len(lines)*22)/2
        for l in lines: w2=W(d,l,flbl)[0]; d.text((lx-w2/2,yy),l,font=flbl,fill=INK); yy+=22
    # 凡例
    ly=cy+R+82
    d.ellipse([cx-176,ly,cx-160,ly+16],fill=BLUE); d.text((cx-152,ly-3),"客観(支援者)",font=fleg,fill=INK)
    d.ellipse([cx+16,ly,cx+32,ly+16],fill=GREEN); d.text((cx+40,ly-3),"本人の主観(mynameis)",font=fleg,fill=INK)
    # ---- 成果3指標カード ----
    cards=[("能力スコアの変化","向上 7 ／ 低下 1","平均Δ +0.8(8項目)",ORANGE),
           ("モニタリング達成度","50 %","平均 3 / 5(6項目)",TEAL),
           ("主観×客観の一致","78 %","本人の見立てと支援者評価の近さ",ORANGE)]
    cx0=860; cw=720; ch=128; gap=28; y0=62
    fct=font(20); fcv=font(42,True); fcs=font(17)
    for i,(t,v,sub,col) in enumerate(cards):
        yy=y0+i*(ch+gap)
        d.rounded_rectangle([cx0,yy,cx0+cw,yy+ch],radius=18,fill=CREAM,outline=STROKE,width=2)
        d.rectangle([cx0+2,yy+18,cx0+10,yy+ch-18],fill=col)
        d.text((cx0+34,yy+18),t,font=fct,fill=GRAY)
        d.text((cx0+34,yy+48),v,font=fcv,fill=col)
        d.text((cx0+34,yy+100),sub,font=fcs,fill=GRAY)
    # ---- キャプション ----
    cap="0〜10は個人内評価＝他児比較ではなく「過去の自分からの成長」　／　本人の主観は mynameis 連携で取り込み　／　別添PDFで計画書に添付"
    d.text(((W0-W(d,cap,fcap)[0])/2, H0-36), cap, font=fcap, fill=GRAY)
    p=os.path.join(FIG,"betten_sample.png"); im.save(p); return p

def wrap(text, n):
    out=[]; line=""
    for ch in text:
        line+=ch
        if len(line)>=n: out.append(line); line=""
    if line: out.append(line)
    return out

if __name__=="__main__":
    for fn in (fig_loop,fig_distill,fig_twowheel,fig_curation,fig_betten):
        print("wrote", fn())
