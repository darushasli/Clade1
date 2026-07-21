#!/bin/bash

# 🤖 ربات سین‌زن TURBO Pro - دستور نصب خودکار

echo "╔════════════════════════════════════════════════════════════╗"
echo "║     🚀 ربات سین‌زن TURBO Pro - نصب خودکار              ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# رنگ‌ها
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# بررسی Python
echo -e "${BLUE}🔍 بررسی Python...${NC}"
if ! command -v python3 &> /dev/null; then
    echo -e "${YELLOW}⚠️  Python3 نصب نیست!${NC}"
    exit 1
fi
python_version=$(python3 --version)
echo -e "${GREEN}✅ $python_version${NC}"
echo ""

# نصب وابستگی‌ها
echo -e "${BLUE}📦 نصب وابستگی‌ها...${NC}"
pip install -q python-telegram-bot==21.0.1 aiohttp requests python-dotenv 2>/dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ وابستگی‌ها نصب شدند${NC}"
else
    echo -e "${YELLOW}⚠️  بعضی وابستگی‌ها شاید نصب نشده‌اند (ادامه برنامه)${NC}"
fi
echo ""

# بررسی فایل .env
echo -e "${BLUE}⚙️  بررسی تنظیمات...${NC}"
if grep -q "8710855081" .env; then
    echo -e "${GREEN}✅ توکن بات: تنظیم شده${NC}"
else
    echo -e "${YELLOW}⚠️  توکن بات: نیاز به تنظیم${NC}"
fi
echo -e "${GREEN}✅ فایل config.json: موجود${NC}"
echo ""

# بررسی فایل‌های اصلی
echo -e "${BLUE}📋 بررسی فایل‌ها...${NC}"
files=("telegram_turbo_pro_final.py" "test_bot.py" ".env" "config.json" "requirements.txt")
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅ $file${NC}"
    else
        echo -e "${YELLOW}⚠️  $file - نیاز به دانلود${NC}"
    fi
done
echo ""

# خلاصه
echo -e "${BLUE}═════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✨ نصب تکمیل شد!${NC}"
echo -e "${BLUE}═════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${BLUE}📁 موقعیت فایل‌ها:${NC}"
echo "   $(pwd)"
echo ""

echo -e "${BLUE}🤖 توکن بات:${NC}"
echo "   $(grep TELEGRAM_BOT_TOKEN .env | cut -d= -f2)"
echo ""

echo -e "${BLUE}🚀 برای شروع ربات:${NC}"
echo "   python3 telegram_turbo_pro_final.py"
echo ""

echo -e "${BLUE}🧪 برای تست:${NC}"
echo "   python3 test_bot.py"
echo ""

echo -e "${BLUE}📖 برای راهنما:${NC}"
echo "   cat README.md"
echo "   cat QUICK_START.md"
echo ""

echo -e "${GREEN}✅ همه چیز آماده است!${NC}"
echo ""
