import os
import re
import pymysql
import pandas as pd
import plotly.graph_objects as go

# 1. 自動換行函式 (每達一定長度或遇分隔符時插入 <br>)
def wrap_metabolite_name(name, max_len=18):
    if not isinstance(name, str):
        return ""
    
    # 若長度較短則不處理
    if len(name) <= max_len:
        return name
    
    # 針對常見化學名稱連接符號進行適度拆分
    # 在  ')', ']', ' ' 後方切分，避免字串過長截斷
    words = re.split(r'(\)|\s|\])', name)
    lines = []
    current_line = ""
    
    for word in words:
        if not word:
            continue
        if len(current_line) + len(word) <= max_len:
            current_line += word
        else:
            if current_line:
                lines.append(current_line)
            current_line = word
            
    if current_line:
        lines.append(current_line)
        
    return "<br>".join(lines)


# 2. 資料庫連線 (請依您的 MySQL 設定修改密碼)
db_config = {
    "host": "127.0.0.1",
    "user": "root",
    "password": "",  # 請填入您的資料庫密碼
    "database": "metabolites",
    "charset": "utf8mb4"
}

conn = pymysql.connect(**db_config)

# 3. 讀取兩張資料表
query_pdc546 = """
    SELECT DMTDB_ID, metabolite_name, average_expression 
    FROM cptac3_pdc000546_expressiondata_max 
    ORDER BY CAST(SUBSTRING(DMTDB_ID, 6) AS UNSIGNED) ASC
"""
query_pdc552 = """
    SELECT DMTDB_ID, metabolite_name, average_expression 
    FROM cptac3_pdc000552_expressiondata_max 
    ORDER BY CAST(SUBSTRING(DMTDB_ID, 6) AS UNSIGNED) ASC
"""

df_546 = pd.read_sql(query_pdc546, conn)
df_552 = pd.read_sql(query_pdc552, conn)
conn.close()

# 4. 合併資料
df_merged = pd.merge(
    df_546[['DMTDB_ID', 'metabolite_name', 'average_expression']],
    df_552[['DMTDB_ID', 'metabolite_name', 'average_expression']],
    on='DMTDB_ID',
    how='outer',
    suffixes=('_pdc546', '_pdc552')
)

df_merged['metabolite_name'] = df_merged['metabolite_name_pdc546'].combine_first(df_merged['metabolite_name_pdc552'])
df_merged['id_num'] = df_merged['DMTDB_ID'].apply(lambda x: int(x.replace('DMTDB', '')) if pd.notnull(x) else 0)
df_merged = df_merged.sort_values('id_num').reset_index(drop=True)

# 產生換行後的 X 軸標籤
df_merged['wrapped_name'] = df_merged['metabolite_name'].apply(wrap_metabolite_name)

# 5. 建立 Plotly 圖表
fig = go.Figure()

# CPTAC-3 (PDC000546)
fig.add_trace(go.Scatter(
    x=df_merged['wrapped_name'],
    y=df_merged['average_expression_pdc546'],
    customdata=df_merged['metabolite_name'], # 懸停提示依然顯示完整不換行的名稱
    mode='lines+markers',
    name='CPTAC-3 (PDC000546)',
    line=dict(color='#2563eb', width=2),
    marker=dict(size=6),
    hovertemplate='<b>%{customdata}</b><br>PDC000546: %{y:,.2f}<extra></extra>'
))

# CPTAC-3 (PDC000552)
fig.add_trace(go.Scatter(
    x=df_merged['wrapped_name'],
    y=df_merged['average_expression_pdc552'],
    customdata=df_merged['metabolite_name'],
    mode='lines+markers',
    name='CPTAC-3 (PDC000552)',
    line=dict(color='#dc2626', width=2),
    marker=dict(size=6),
    hovertemplate='<b>%{customdata}</b><br>PDC000552: %{y:,.2f}<extra></extra>'
))

# 6. 版面與尺寸設定
total_items = len(df_merged)
item_width = 60
calculated_width = max(1150, total_items * item_width)

fig.update_layout(
    title=dict(
        text='',
        x=0.01,
        xanchor='left',
        font=dict(size=16)
    ),
    xaxis=dict(
        title=None,
        type='category',
        tickangle=-45,
        tickfont=dict(size=10),
        showgrid=True,
        gridcolor='#f1f5f9',
        range=[-0.5, total_items - 0.5],
        automargin=True
    ),
    yaxis=dict(
        title='Average Expression',
        autorange=True,
        showgrid=True,
        gridcolor='#e2e8f0',
        nticks=16,                       # 增加 Y 軸主要刻度數量，呈現更細節的變化
        minor=dict(
            ticks="inside",
            showgrid=True,
            gridcolor='#f8fafc'          # 加入次要網格輔助觀察
        ),
        tickformat="~s",                 # 刻度使用簡潔的單位 (如 20M, 40M)
        fixedrange=True
    ),
    width=calculated_width,
    height=680,                          # 將圖表整體高度拉大到 680px
    margin=dict(l=70, r=20, t=50, b=160),# 底部預留 160px 給多行換行後的文字標籤
    hovermode='x unified',
    template='plotly_white',
    legend=dict(
        orientation="h",
        yanchor="bottom",
        y=1.02,
        xanchor="left",
        x=0.01
    )
)

# 7. 輸出檔案
current_dir = os.path.dirname(os.path.abspath(__file__))
output_path = os.path.join(current_dir, "analysis", "expression_chart.html")
os.makedirs(os.path.dirname(output_path), exist_ok=True)

fig.write_html(output_path, full_html=False, include_plotlyjs='cdn')
print(f"圖表已重新生成至: {output_path}")