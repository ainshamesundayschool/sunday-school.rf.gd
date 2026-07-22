#!/usr/bin/env bash
# Sunday School WhatsApp OTP Bot Service Helper Script
# Usage: ./scripts/run_whatsapp_bot.sh {start|stop|restart|logs}

COMMAND="${1:-start}"
WORKSPACE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$WORKSPACE_DIR"

case "$COMMAND" in
    start)
        echo "🚀 Starting Sunday School WhatsApp OTP Bot in background..."
        lsof -ti:5000 | xargs kill -9 2>/dev/null || true
        nohup sh -c 'NODE_OPTIONS="--max-old-space-size=256" PORT=5000 pnpm --filter @workspace/api-server run dev' > bot.log 2>&1 &
        sleep 2
        echo "✅ Bot started successfully! View live logs using: ./scripts/run_whatsapp_bot.sh logs"
        ;;
    stop)
        echo "🛑 Stopping WhatsApp OTP Bot..."
        lsof -ti:5000 | xargs kill -9 2>/dev/null || true
        echo "✅ Bot process stopped."
        ;;
    logs|status)
        if [ -f "bot.log" ]; then
            echo "📋 Recent WhatsApp Bot Logs:"
            tail -n 30 bot.log
        else
            echo "⚠️ No bot.log found. Start the bot with: ./scripts/run_whatsapp_bot.sh start"
        fi
        ;;
    restart)
        echo "🔄 Restarting WhatsApp Bot..."
        lsof -ti:5000 | xargs kill -9 2>/dev/null || true
        sleep 1
        nohup sh -c 'NODE_OPTIONS="--max-old-space-size=256" PORT=5000 pnpm --filter @workspace/api-server run dev' > bot.log 2>&1 &
        sleep 2
        echo "✅ Bot restarted successfully!"
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|logs}"
        exit 1
        ;;
esac
