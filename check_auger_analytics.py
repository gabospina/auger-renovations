import json, os

f = 'admin/data/analytics_events.json'
if not os.path.exists(f):
    print("File does not exist yet — no events tracked.")
else:
    with open(f, encoding='utf-8') as fp:
        events = json.load(fp)
    print(f"Total events: {len(events)}\n")
    for e in events[-5:]:
        print(" ", e)